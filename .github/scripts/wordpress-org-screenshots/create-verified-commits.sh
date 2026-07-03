#!/usr/bin/env bash
#
# Commits the working tree's .wordpress-org changes to a remote branch via
# GitHub's GraphQL createCommitOnBranch mutation.
#
# Commits created through that mutation are signed by GitHub itself and show
# as "Verified" — which the develop branch protection requires, and which a
# plain `git commit` inside a workflow cannot satisfy without managing GPG
# keys in secrets.
#
# The mutation carries file contents base64-encoded in the request body, so
# additions are chunked into multiple commits to stay well below the API's
# request-size limits. Deletions ride along with the first chunk.
#
# Usage:
#   create-verified-commits.sh <owner/repo> <branch> <expected-head-oid> <message>
#
# The branch must already exist on the remote at <expected-head-oid>.
# Requires: git, jq, curl, base64; GITHUB_TOKEN in the environment.

set -euo pipefail

REPO="$1"
BRANCH="$2"
HEAD_OID="$3"
MESSAGE="$4"

BASE_REF="origin/develop"
PATHSPEC=".wordpress-org"
# Raw bytes of file content per commit; base64 inflates by ~4/3, leaving
# comfortable headroom under the GraphQL request-size limit.
CHUNK_LIMIT=$(( 512 * 1024 ))

QUERY='mutation($input: CreateCommitOnBranchInput!) {
  createCommitOnBranch(input: $input) { commit { oid } }
}'

# Tracked modifications and new untracked screenshots are additions;
# tracked files gone from the working tree are deletions.
mapfile -t ADDITIONS < <(
	{
		git diff --name-only --diff-filter=d "$BASE_REF" -- "$PATHSPEC"
		git ls-files --others --exclude-standard -- "$PATHSPEC"
	} | sort -u
)
mapfile -t DELETIONS < <( git diff --name-only --diff-filter=D "$BASE_REF" -- "$PATHSPEC" )

if [ "${#ADDITIONS[@]}" -eq 0 ] && [ "${#DELETIONS[@]}" -eq 0 ]; then
	echo "No screenshot changes to commit for ${BRANCH}."
	exit 1
fi

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

# Deletions only ship with the first commit.
jq -n '[]' > "$WORKDIR/deletions.json"
if [ "${#DELETIONS[@]}" -gt 0 ]; then
	printf '%s\n' "${DELETIONS[@]}" | jq -R '{path: .}' | jq -s '.' > "$WORKDIR/deletions.json"
fi

commit_chunk() {
	local part="$1"

	jq -n \
		--arg repo "$REPO" \
		--arg branch "$BRANCH" \
		--arg oid "$HEAD_OID" \
		--arg msg "$MESSAGE" \
		--arg part "$part" \
		--slurpfile additions "$WORKDIR/additions.json" \
		--slurpfile deletions "$WORKDIR/deletions.json" \
		'{
			branch: { repositoryNameWithOwner: $repo, branchName: $branch },
			expectedHeadOid: $oid,
			message: { headline: ( if $part == "1" then $msg else ( $msg + " (part " + $part + ")" ) end ) },
			fileChanges: { additions: $additions[0], deletions: $deletions[0] }
		}' > "$WORKDIR/input.json"

	jq -n --arg q "$QUERY" --slurpfile input "$WORKDIR/input.json" \
		'{query: $q, variables: {input: $input[0]}}' > "$WORKDIR/body.json"

	local response
	response="$(curl -sf \
		-H "Authorization: bearer ${GITHUB_TOKEN}" \
		-H "Content-Type: application/json" \
		--data-binary @"$WORKDIR/body.json" \
		https://api.github.com/graphql)"

	if jq -e '.errors' <<<"$response" > /dev/null; then
		echo "createCommitOnBranch failed:" >&2
		jq '.errors' <<<"$response" >&2
		exit 1
	fi

	HEAD_OID="$(jq -r '.data.createCommitOnBranch.commit.oid' <<<"$response")"
	echo "Created verified commit ${HEAD_OID} (chunk ${part})."

	# Subsequent chunks start clean.
	jq -n '[]' > "$WORKDIR/additions.json"
	jq -n '[]' > "$WORKDIR/deletions.json"
}

jq -n '[]' > "$WORKDIR/additions.json"
PART=1
CHUNK_BYTES=0

for file in "${ADDITIONS[@]}"; do
	size="$(wc -c < "$file")"

	# Flush the current chunk before this file would push it past the limit.
	if [ "$CHUNK_BYTES" -gt 0 ] && [ $(( CHUNK_BYTES + size )) -gt "$CHUNK_LIMIT" ]; then
		commit_chunk "$PART"
		PART=$(( PART + 1 ))
		CHUNK_BYTES=0
	fi

	jq --arg path "$file" --arg contents "$(base64 -w0 "$file")" \
		'. += [{path: $path, contents: $contents}]' \
		"$WORKDIR/additions.json" > "$WORKDIR/additions.json.tmp"
	mv "$WORKDIR/additions.json.tmp" "$WORKDIR/additions.json"
	CHUNK_BYTES=$(( CHUNK_BYTES + size ))
done

# Flush the remainder (also handles the deletions-only case).
if [ "$CHUNK_BYTES" -gt 0 ] || jq -e 'length > 0' "$WORKDIR/deletions.json" > /dev/null; then
	commit_chunk "$PART"
fi

echo "Branch ${BRANCH} is at ${HEAD_OID}."
