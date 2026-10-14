@extends('legal.layout')

@section('title', 'Data Deletion Status')

@section('content')
    <section class="space-y-4">
        <form method="GET" action="{{ url('/data-deletion/status') }}" class="flex flex-col sm:flex-row gap-3">
            <label for="code" class="sr-only">Confirmation code</label>
            <input id="code" name="code" value="{{ $code }}" placeholder="Confirmation code" maxlength="64"
                class="flex-1 rounded-lg bg-slate-900 border border-white/15 px-4 py-2 text-white placeholder-slate-500" />
            <button class="rounded-lg bg-sky-600 hover:bg-sky-500 px-5 py-2 font-medium text-white">Check status</button>
        </form>

        @if ($code !== '')
            @if ($request)
                <div class="rounded-lg border border-white/10 bg-slate-900 px-5 py-4 space-y-1">
                    <p>Confirmation code: <span class="font-mono text-white">{{ $request->code }}</span></p>
                    <p>Received: {{ $request->created_at->toDayDateTimeString() }} UTC</p>
                    @if ($request->status === \App\Models\DataDeletionRequest::STATUS_COMPLETED)
                        <p class="text-emerald-300">Status: completed on {{ $request->completed_at?->toDayDateTimeString() }} UTC. Your data has been deleted.</p>
                    @else
                        <p class="text-amber-300">Status: in progress. Deletion completes within 7 days of the request.</p>
                    @endif
                </div>
            @else
                <p class="text-red-300">We could not find a deletion request with that code.</p>
            @endif
        @endif

        <p>Questions? Email <a class="text-sky-300 underline" href="mailto:{{ config('clippipeline.support_email') }}">{{ config('clippipeline.support_email') }}</a>.</p>
    </section>
@endsection
