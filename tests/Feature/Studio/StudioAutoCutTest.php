<?php

namespace Tests\Feature\Studio;

use App\Studio\Models\StudioAsset;
use App\Studio\Services\AutoCutter;
use Illuminate\Support\Facades\Storage;

class StudioAutoCutTest extends StudioTestCase
{
    private function words(): array
    {
        // "Hello there. um So [2 s pause] this is great."
        return ['language' => 'en', 'words' => [
            ['w' => 'Hello', 's' => 0.50, 'e' => 0.80], ['w' => 'there.', 's' => 0.85, 'e' => 1.20],
            ['w' => 'um', 's' => 1.40, 'e' => 1.70], ['w' => 'So', 's' => 1.90, 'e' => 2.10],
            ['w' => 'this', 's' => 4.20, 'e' => 4.40], ['w' => 'is', 's' => 4.45, 'e' => 4.55], ['w' => 'great.', 's' => 4.60, 'e' => 5.00],
        ]];
    }

    public function test_cutter_drops_pauses_and_fillers_on_word_boundaries(): void
    {
        $asset = new StudioAsset(['original_name' => 'a.mp4', 'probe' => ['silence' => ['starts' => [2.2, 5.1], 'ends' => [4.1, 8.0]]]]);
        $asset->id = 7;
        $main = [['id' => 's1', 'type' => 'clip', 'asset_id' => 7, 'in' => 0, 'out' => 8, 'transition_in' => ['template' => 'swipe', 'duration' => 0.4]]];

        $out = (new AutoCutter)->cut($main, [7 => $asset], [7 => $this->words()], [
            'remove_silences' => true, 'silence_min_seconds' => 0.6, 'remove_fillers' => true, 'filler_words' => ['um', 'uh'], 'max_segment_seconds' => null,
        ]);

        $ranges = array_map(fn ($s) => [$s['in'], $s['out']], $out['main']);
        // Piece 1 starts 0.1 s before "Hello" and ends before "um".
        $this->assertEqualsWithDelta(0.40, $ranges[0][0], 0.001);
        $this->assertLessThan(1.40, $ranges[0][1]);
        $this->assertGreaterThanOrEqual(1.35, $ranges[0][1]);
        // "So" is kept, the 2 s pause is gone, the last piece ends ≥ 0.2 s after "great.".
        $this->assertEqualsWithDelta(1.80, $ranges[1][0], 0.001);
        $last = end($ranges);
        $this->assertEqualsWithDelta(4.10, $last[0], 0.001);
        $this->assertGreaterThanOrEqual(5.20, $last[1]);
        $this->assertLessThan(5.5, $last[1]);
        // Only the first piece keeps the transition.
        $this->assertArrayHasKey('transition_in', $out['main'][0]);
        $this->assertArrayNotHasKey('transition_in', $out['main'][1]);
        $this->assertStringContainsString('filler "um"', $out['cut_list'][0]);
    }

    public function test_reaction_after_last_word_is_kept(): void
    {
        $asset = new StudioAsset(['probe' => ['silence' => ['starts' => [6.0], 'ends' => [8.0]]]]);
        $asset->id = 1;
        $t = ['words' => [['w' => 'punchline!', 's' => 4.0, 'e' => 4.5]]];
        $out = (new AutoCutter)->cut([['id' => 's', 'type' => 'clip', 'asset_id' => 1, 'in' => 0, 'out' => 8]], [1 => $asset], [1 => $t],
            ['remove_silences' => true, 'silence_min_seconds' => 0.6, 'remove_fillers' => true, 'filler_words' => [], 'max_segment_seconds' => null]);
        // Laughter until the silence at 6.0 is kept, capped at 1.5 s after the word.
        $this->assertEqualsWithDelta(6.0, $out['main'][0]['out'], 0.001);
    }

    public function test_max_segment_splits_and_alternates_zoom(): void
    {
        $words = [];
        for ($i = 0; $i < 20; $i++) {
            $words[] = ['w' => $i % 5 === 4 ? 'word.' : 'word', 's' => $i * 0.5, 'e' => $i * 0.5 + 0.4];
        }
        $asset = new StudioAsset(['probe' => []]);
        $asset->id = 1;
        $out = (new AutoCutter)->cut([['id' => 's', 'type' => 'clip', 'asset_id' => 1, 'in' => 0, 'out' => 10]], [1 => $asset], [1 => ['words' => $words]],
            ['remove_silences' => true, 'silence_min_seconds' => 0.6, 'remove_fillers' => true, 'filler_words' => [], 'max_segment_seconds' => 2.5]);
        $this->assertGreaterThan(2, count($out['main']));
        foreach ($out['main'] as $s) {
            $this->assertLessThanOrEqual(2.5 * 1.25 + 0.01, $s['out'] - $s['in']);
        }
        $this->assertSame([1.0, 1.08], [$out['main'][0]['zoom'], $out['main'][1]['zoom']]);
    }

    public function test_auto_cut_endpoint_updates_edl_and_can_be_undone(): void
    {
        $p = $this->project();
        $a = $this->readyAsset($p);
        Storage::disk('local')->put('tr.json', json_encode($this->words()));
        $a->forceFill(['transcript_path' => 'tr.json'])->save();
        $edl = $p->edl;
        $edl['main'][] = ['id' => 's1', 'type' => 'clip', 'asset_id' => $a->id, 'in' => 0, 'out' => 8];
        $p->forceFill(['edl' => $edl])->save();

        $res = $this->postJson("/api/studio/projects/{$p->id}/auto-cut", ['remove_fillers' => true])->assertStatus(202);
        $res->assertJsonPath('status', 'done')->assertJsonPath('type', 'auto_cut');
        $this->assertNotEmpty($res->json('result.cut_list'));
        $p->refresh();
        $this->assertGreaterThan(1, count($p->edl['main']));
        $this->assertSame(2, $p->revision);

        $this->postJson("/api/studio/projects/{$p->id}/undo-auto-cut")->assertOk()->assertJsonCount(1, 'edl.main')->assertJsonPath('can_undo_auto_cut', false);
        $this->postJson("/api/studio/projects/{$p->id}/undo-auto-cut")->assertStatus(422);
    }
}
