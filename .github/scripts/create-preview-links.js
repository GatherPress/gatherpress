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
console.log('createBlueprint',context);
	const { issue: { number, repo, owner } = {} } = context;
	const workflow = 'Build%20GatherPress%20Plugin%20Zip';
	const artifact = 'gatherpress-pr';
	const proxy = 'https://hub.carsten-bach.de/gatherpress/plugin-proxy.php';
	// const zipArtifactUrl = `?org=GatherPress&repo=gatherpress&workflow=Build%20GatherPress%20Plugin%20Zip&artifact=gatherpress-pr&pr=${number}`;
	const zipArtifactUrl = `${proxy}/?org=${owner}&repo=${repo}&workflow=${workflow}&artifact=${artifact}&pr=${number}`;

	// TODO
	// Verify that the PR exists and that GitHub CI finished building it
	// ...

	// const { owner, repo } = context;
	// const url = `https://github-proxy.com/proxy/?repo=${owner}/${repo}&pr=${pr}`; // full repo (40MB) and NO BUILD
	// const url = `https://github-proxy.com/proxy/?repo=${owner}/${repo}&pr=${pr}`; // distributed (1.3MB) and BUILD
	const template = {
		steps: [
/* 			{
				step: 'login',
				username: 'admin',
				password: 'password',
			},
			{
				step: 'installPlugin',
				pluginZipFile: {
					resource: 'url',
					url,
				},
			}, */
			{
				step: 'login',
				username: 'admin',
				password: 'password',
			},
			{
				step: 'setSiteOptions',
				options: {
					blogname: `${owner}/${repo} PR #${number}`,
					blogdescription: `Testing pull_request #${number} with playground`,
				}
			},
			{
				step: 'mkdir',
				path: '/wordpress/pr',
			},
			/*
			* This is the most important step.
			* It download the built plugin zip file exposed by GitHub CI.
			*
			* Because the zip file is not publicly accessible, we use the
			* plugin-proxy API endpoint to download it. The source code of
			* that endpoint is available at:
			* https://github.com/WordPress/wordpress-playground/blob/trunk/packages/playground/website/public/plugin-proxy.php
			*/
			{
				step: 'writeFile',
				path: '/wordpress/pr/pr.zip',
				data: {
					resource: 'url',
					url: zipArtifactUrl,
					caption: `Downloading ${owner}/${repo} PR #${number}`,
				},
				progress: {
					weight: 2,
					caption: `Applying ${owner}/${repo} PR #${number}`,
				},
			},
			/**
			 * GitHub CI artifacts are doubly zipped:
			*
			* pr.zip
			*    gatherpress.zip
			*       gatherpress/
			*          gatherpress.php
			*          ... other files ...
			*
			* This step extracts the inner zip file so that we get
			* access directly to gatherpress.zip and can use it to
			* install the plugin.
			*/
			{
				step: 'unzip',
				zipPath: '/wordpress/pr/pr.zip',
				extractToPath: '/wordpress/pr',
			},
			{
				step: 'installPlugin',
				pluginZipFile: {
					resource: 'vfs',
					path: '/wordpress/pr/gatherpress.zip',
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
	console.log('createPreviewLinksComment', context);
	const blueprint = createBlueprint(
		context.repo,
		context.payload.pull_request.number
	)

	const previewLinks = `
- [Preview changes for **${context.repo.repo}**](https://playground.wordpress.net/#${blueprint})
`;

	const comment = `
You can preview these changes by following the link below:

${previewLinks}

**⚠️ Note:** The preview is created using github-proxy.com, which loads the full (40MB) repo and does NO BUILD.
`;
// **⚠️ Note:** The preview sites are created using [WordPress Playground](https://wordpress.org/playground/). You can add content, edit settings, and test the themes as you would on a real site, but please note that changes are not saved between sessions.

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
		// await github.rest.issues.updateComment({
		// 	comment_id: existingComment.id,
		// 	...commentObject,
		// });
		// return;
		// Do not update, but delete and recreate Comment to have a new one after last commit.
		await github.rest.issues.deleteComment({
			comment_id: existingComment.id,
			...commentObject,
		});
	}

	// Create a new comment if one doesn't exist
	github.rest.issues.createComment({
		issue_number: context.payload.pull_request.number,
		...commentObject,
	});
}

module.exports = createPreviewLinksComment;