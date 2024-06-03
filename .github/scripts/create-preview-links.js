/**
 * Based on:
 * https://github.com/Automattic/themes/blob/a0c9b91f827f46ed60c502d41ef881b2f0552f03/.github/scripts/create-preview-links.js
 */

/*
 * This function creates a WordPress Playground blueprint JSON string for a theme.
 *
 * @param {string} themeSlug - The slug of the theme to create a blueprint for.
 * @param {string} branch - The branch where the theme changes are located.
 * @returns {string} - A JSON string representing the blueprint.
 */
function createBlueprint(context, pr) {
	const { owner, repo } = context;
	const proxy = 'https://github-proxy.com/proxy/';
	const template = {
		steps: [
			{
				step: 'login',
				username: 'admin',
				password: 'password',
			},
			{
				step: 'installPlugin',
				pluginZipFile: {
					resource: 'url',
					url: `${proxy}?repo=${owner}/${repo}&pr=${pr}`,
				},
			},
		],
	};

	return JSON.stringify(template);
}

/*
 * This function creates a comment on a PR with preview links for ...
 * It is used by `preview-playground` workflow.
 *
 * @param {object} github - An authenticated instance of the GitHub API.
 * @param {object} context - The context of the event that triggered the action.
 */
async function createPreviewLinksComment(github, context) {
	const previewLinks = `
- [Preview changes for **${context.repo.repo}**](https://playground.wordpress.net/#${createBlueprint(
				context.repo,
				context.payload.pull_request.head.ref
			)})
`;

	const comment = `
You can preview these changes by following the links below:

${previewLinks}

I will update this comment with the latest preview links as you push more changes to this PR.
**⚠️ Note:** The preview sites are created using [WordPress Playground](https://wordpress.org/playground/). You can add content, edit settings, and test the themes as you would on a real site, but please note that changes are not saved between sessions.
`;

	const repoData = {
		owner: context.repo.owner,
		repo: context.repo.repo,
	};

	// Check if a comment already exists and update it if it does
	const { data: comments } = await github.rest.issues.listComments({
		issue_number: context.payload.pull_request.number,
		...repoData,
	});
	const existingComment = comments.find(
		(comment) =>
			comment.user.login === 'github-actions[bot]' &&
			comment.body.startsWith('### Preview changes')
	);
	const commentObject = {
		body: `### Preview changes\n${comment}`,
		...repoData,
	};

	if (existingComment) {
		await github.rest.issues.updateComment({
			comment_id: existingComment.id,
			...commentObject,
		});
		return;
	}

	// Create a new comment if one doesn't exist
	github.rest.issues.createComment({
		issue_number: context.payload.pull_request.number,
		...commentObject,
	});
}

module.exports = createPreviewLinksComment;