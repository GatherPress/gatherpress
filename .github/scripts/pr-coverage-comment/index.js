/**
 * Create or update a PR comment with coverage check results.
 *
 * This script posts coverage results as a comment on pull requests,
 * replacing any existing coverage comment to avoid clutter.
 */

const COMMENT_TITLE = '### Test Coverage Report';

/**
 * Format the coverage output for display in markdown.
 *
 * @param {string} phpOutput PHP coverage check output.
 * @param {string} jsOutput  JavaScript coverage check output.
 * @param {string} phpStatus PHP coverage check status ('success' or 'failure').
 * @param {string} jsStatus  JavaScript coverage check status ('success' or 'failure').
 * @return {string} Formatted markdown comment body.
 */
function formatCoverageComment(phpOutput, jsOutput, phpStatus, jsStatus) {
	const overallStatus = phpStatus === 'success' && jsStatus === 'success';
	const statusIcon = overallStatus ? '✅' : '❌';
	const statusText = overallStatus ? 'All coverage checks passed!' : 'Coverage checks need attention';

	let body = `${statusIcon} **${statusText}**\n\n`;

	// Add PHP coverage section.
	if (phpOutput && phpOutput.trim()) {
		const phpStatusIcon = phpStatus === 'success' ? '✅' : '❌';
		body += `<details${phpStatus === 'failure' ? ' open' : ''}>\n`;
		body += `<summary>${phpStatusIcon} <strong>PHP Coverage</strong></summary>\n\n`;
		body += '```\n';
		body += phpOutput.trim();
		body += '\n```\n';
		body += '</details>\n\n';
	}

	// Add JavaScript coverage section.
	if (jsOutput && jsOutput.trim()) {
		const jsStatusIcon = jsStatus === 'success' ? '✅' : '❌';
		body += `<details${jsStatus === 'failure' ? ' open' : ''}>\n`;
		body += `<summary>${jsStatusIcon} <strong>JavaScript Coverage</strong></summary>\n\n`;
		body += '```\n';
		body += jsOutput.trim();
		body += '\n```\n';
		body += '</details>\n\n';
	}

	// Add footer.
	body += '---\n';
	body += '*This comment is automatically updated on each push.*';

	return body;
}

/**
 * Main function to create or update the PR coverage comment.
 *
 * @param {Object} github  An authenticated GitHub API instance.
 * @param {Object} context The context of the event.
 * @param {string} phpOutput PHP coverage check output.
 * @param {string} jsOutput  JavaScript coverage check output.
 * @param {string} phpStatus PHP coverage check status ('success' or 'failure').
 * @param {string} jsStatus  JavaScript coverage check status ('success' or 'failure').
 */
async function createCoverageComment(github, context, phpOutput, jsOutput, phpStatus, jsStatus) {
	const prNumber = context.payload.pull_request.number;

	const repoData = {
		owner: context.repo.owner,
		repo: context.repo.repo,
	};

	// Format the comment body.
	const commentBody = formatCoverageComment(phpOutput, jsOutput, phpStatus, jsStatus);

	// Check if a coverage comment already exists.
	const { data: comments } = await github.rest.issues.listComments({
		issue_number: prNumber,
		...repoData,
	});

	const existingComment = comments.find(
		(comment) =>
			comment.user.login === 'github-actions[bot]' &&
			comment.body.startsWith(COMMENT_TITLE)
	);

	const commentObject = {
		body: `${COMMENT_TITLE}\n\n${commentBody}`,
		...repoData,
	};

	// If an existing comment is found, update it instead of creating a new one.
	if (existingComment) {
		await github.rest.issues.updateComment({
			comment_id: existingComment.id,
			...commentObject,
		});
	} else {
		await github.rest.issues.createComment({
			issue_number: prNumber,
			...commentObject,
		});
	}
}

module.exports = createCoverageComment;
