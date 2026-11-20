#!/usr/bin/env bash
# Creates the Always Free Arm server for the Studio render worker, from Oracle Cloud Shell.
# No web forms needed (handy when the console's create page is blank on a phone).
#
#   curl -fsSL https://raw.githubusercontent.com/Manlikenonso1k/clippipeline/main/render-worker/oracle-create-instance.sh -o oc.sh
#   bash oc.sh
#
# It makes (or reuses) a login key, a network with internet access, and an Ubuntu 24.04
# VM.Standard.A1.Flex instance, trying every availability domain and smaller sizes when Oracle
# says "Out of capacity". Safe to re-run: existing pieces with the same names are reused.
set -uo pipefail

NAME=clippipeline-worker
KEY=~/.ssh/oracle_worker
C="${OCI_TENANCY:-${COMPARTMENT_ID:-}}"
[ -n "$C" ] || { echo "Run this in Oracle Cloud Shell (or set COMPARTMENT_ID to your tenancy OCID)."; exit 1; }

say() { echo; echo "==> $*"; }

say "Login key"
if [ ! -f "$KEY" ]; then
  ssh-keygen -q -t rsa -b 4096 -f "$KEY" -N "" && echo "    created $KEY"
else
  echo "    using $KEY"
fi

say "Existing server?"
EXISTING=$(oci compute instance list -c "$C" --display-name "$NAME" --lifecycle-state RUNNING --query 'data[0].id' --raw-output 2>/dev/null)
if [ -n "$EXISTING" ] && [ "$EXISTING" != "null" ]; then
  IP=$(oci compute instance list-vnics --instance-id "$EXISTING" --query 'data[0]."public-ip"' --raw-output)
  echo "    $NAME is already running. Connect with:"
  echo "    ssh -i $KEY ubuntu@$IP"
  exit 0
fi
echo "    none, creating one"

say "Network"
VCN=$(oci network vcn list -c "$C" --display-name worker-vcn --query 'data[0].id' --raw-output 2>/dev/null)
if [ -z "$VCN" ] || [ "$VCN" = "null" ]; then
  VCN=$(oci network vcn create -c "$C" --cidr-block 10.0.0.0/16 --display-name worker-vcn \
        --wait-for-state AVAILABLE --query 'data.id' --raw-output) || { echo "Could not create the network."; exit 1; }
  IGW=$(oci network internet-gateway create -c "$C" --vcn-id "$VCN" --is-enabled true --display-name worker-igw \
        --wait-for-state AVAILABLE --query 'data.id' --raw-output)
  RT=$(oci network vcn get --vcn-id "$VCN" --query 'data."default-route-table-id"' --raw-output)
  oci network route-table update --rt-id "$RT" --force \
    --route-rules "[{\"destination\":\"0.0.0.0/0\",\"destinationType\":\"CIDR_BLOCK\",\"networkEntityId\":\"$IGW\"}]" >/dev/null
  echo "    created worker-vcn with internet access"
else
  echo "    reusing worker-vcn"
fi
SUBNET=$(oci network subnet list -c "$C" --vcn-id "$VCN" --display-name worker-subnet --query 'data[0].id' --raw-output 2>/dev/null)
if [ -z "$SUBNET" ] || [ "$SUBNET" = "null" ]; then
  SUBNET=$(oci network subnet create -c "$C" --vcn-id "$VCN" --cidr-block 10.0.0.0/24 --display-name worker-subnet \
           --wait-for-state AVAILABLE --query 'data.id' --raw-output) || { echo "Could not create the subnet."; exit 1; }
fi

say "Ubuntu 24.04 image for Arm"
IMG=$(oci compute image list -c "$C" --operating-system "Canonical Ubuntu" --operating-system-version "24.04" \
      --shape VM.Standard.A1.Flex --sort-by TIMECREATED --sort-order DESC --query 'data[0].id' --raw-output)
[ -n "$IMG" ] && [ "$IMG" != "null" ] || { echo "No Ubuntu 24.04 Arm image found in this region."; exit 1; }
echo "    found"

say "Creating the server (Always Free: up to 4 OCPU / 24 GB)"
ADS=$(oci iam availability-domain list -c "$C" --query 'data[].name' --raw-output | tr -d '[]", ' | grep -v '^$')
ID=""
for SIZE in "4 24" "2 12" "1 6"; do
  set -- $SIZE
  for AD in $ADS; do
    echo "    trying $1 OCPU / $2 GB in $AD ..."
    OUT=$(oci compute instance launch -c "$C" --availability-domain "$AD" --display-name "$NAME" \
          --shape VM.Standard.A1.Flex --shape-config "{\"ocpus\":$1,\"memoryInGBs\":$2}" \
          --image-id "$IMG" --subnet-id "$SUBNET" --assign-public-ip true \
          --ssh-authorized-keys-file "$KEY.pub" --wait-for-state RUNNING \
          --query 'data.id' --raw-output 2>&1)
    if [[ "$OUT" == ocid1.instance* ]]; then ID=$(echo "$OUT" | grep -o 'ocid1.instance[^ ]*' | head -1); break 2; fi
    if echo "$OUT" | grep -qi "capacity"; then echo "    out of capacity there"; continue; fi
    echo "$OUT" | tail -5; echo "Stopped: the error above is not a capacity problem."; exit 1
  done
done
[ -n "$ID" ] || { echo; echo "Oracle has no free Arm capacity right now in any zone. Try again later (often works within hours)."; exit 1; }

IP=$(oci compute instance list-vnics --instance-id "$ID" --query 'data[0]."public-ip"' --raw-output)
say "Done. Server $NAME is running at $IP"
echo "    Wait about 1 minute for it to boot, then connect with:"
echo "    ssh -i $KEY ubuntu@$IP"
