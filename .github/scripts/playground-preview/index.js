const fs = require('fs');
const path = require('path');

/**
 * Attempts to read and parse a per-PR blueprint override file.
 *
 * The file is looked up at `.github/playground/PR-123-blueprint-override.json`
 * in the checked-out PR branch code. When called from `workflow_run`,
 * this requires a separate checkout of the PR head ref (see workflow
 * changes below).
 *
 * @param {string} [filePath] - Path to the override file.
 * @returns {object|null} - Parsed override object, or null if not found / invalid.
 */
function loadBlueprintOverride(filePath) {

	try {
		if (!fs.existsSync(filePath)) {
			console.log(`No blueprint override found at ${filePath}`);
			return null;
		}

		const raw = fs.readFileSync(filePath, 'utf8');
		const override = JSON.parse(raw);

		console.log(`Loaded blueprint override from ${filePath}`);
		return override;
	} catch (err) {
		console.warn(`Warning: Failed to load blueprint override: ${err.message}`);
		return null;
	}
}

/**
 * Validates that an override doesn't try to set protected fields.
 *
 * @param {object} override - The parsed override object.
 * @returns {object} - The sanitized override with protected fields removed.
 */
function sanitizeOverride(override) {
	const sanitized = { ...override };

	// These fields are controlled by the generation script and must not
	// be overridden, because they are either driven by the version matrix
	// or contain PR-specific dynamic values.
	const protectedFields = ['preferredVersions', 'phpExtensionBundles'];

	for (const field of protectedFields) {
		if (field in sanitized) {
			console.warn(
				`Warning: Override tried to set protected field "${field}" — ignoring.`
			);
			delete sanitized[field];
		}
	}

	// Prevent overriding steps directly — use prependSteps / appendSteps instead.
	if ('steps' in sanitized) {
		console.warn(
			'Warning: Override contains "steps" — use "prependSteps" or "appendSteps" instead. Ignoring "steps".'
		);
		delete sanitized.steps;
	}

	return sanitized;
}

/**
 * Merges a sanitized override into the base blueprint template.
 *
 * Merge strategy:
 * - `landingPage`: override replaces base.
 * - `features`: shallow merge (override keys win).
 * - `siteOptions`: merged into the setSiteOptions step.
 * - `prependSteps`: inserted after login + setSiteOptions, before mkdir.
 * - `appendSteps`: appended after all base steps.
 *
 * @param {object} template - The base blueprint object (mutated in place).
 * @param {object} override - The sanitized override object.
 * @returns {object} - The merged blueprint.
 */
function mergeOverride(template, override) {
	// landingPage — simple replacement
	if (override.landingPage) {
		template.landingPage = override.landingPage;
	}

	// features — shallow merge
	if (override.features) {
		template.features = {
			...template.features,
			...override.features,
		};
	}

	// siteOptions — merge into existing setSiteOptions step
	if (override.siteOptions) {
		const siteOptionsStep = template.steps.find(
			(s) => s.step === 'setSiteOptions'
		);
		if (siteOptionsStep) {
			siteOptionsStep.options = {
				...siteOptionsStep.options,
				...override.siteOptions,
			};
		}
	}

	// prependSteps — insert after setSiteOptions, before mkdir
	if (override.prependSteps && Array.isArray(override.prependSteps)) {
		const mkdirIndex = template.steps.findIndex(
			(s) => s.step === 'mkdir'
		);
		const insertAt = mkdirIndex >= 0 ? mkdirIndex : 2; // fallback after login + setSiteOptions
		template.steps.splice(insertAt, 0, ...override.prependSteps);
	}

	// appendSteps — add after all existing steps
	if (override.appendSteps && Array.isArray(override.appendSteps)) {
		template.steps.push(...override.appendSteps);
	}

	return template;
}

/**
 * This function creates the URL to download the built & zipped plugin artifact.
 * It uses a proxy because the artifact is not publicly accessible directly.
 *
 * @param {object} context - The context of the event that triggered the action.
 * @param {number} number - The pull request (PR) number where the plugin changes are located.
 * @returns {string} - The URL to download the zipped plugin artifact.
 */
function createBlueprintUrl(context, number) {
	const { repo, owner } = context;
	const workflow = encodeURI('Playground Preview');  // Encode the workflow name
	const artifact = 'gatherpress-pr'; // GitHub Actions artifact name
	const proxy = 'https://gatherpress.org/playground-preview/plugin-proxy.php';

	return `${proxy}?org=${owner}&repo=${repo}&workflow=${workflow}&artifact=${artifact}&pr=${number}`;
}

/**
 * Creates a WordPress Playground blueprint JSON string.
 * The blueprint specifies the PHP version to use, among other configuration details
 * optionally merging a per-PR override.
 *
 * @param {object} context - The context of the event that triggered the action.
 * @param {number} number - The PR number where the plugin changes are located.
 * @param {string} zipArtifactUrl - The URL where the built plugin artifact can be downloaded.
 * @param {string} phpVersion - The PHP version to use in the WordPress Playground.
 * @param {object|null} override - Optional override loaded from the PR branch.
 * @returns {string} - A JSON string representing the blueprint.
 */
function createBlueprint(context, number, zipArtifactUrl, phpVersion) {
	const { repo, owner } = context;

	// TODO
	// Verify that the PR exists and that GitHub CI finished building it
	// ...

	const template = {
		landingPage: '/wp-admin/post-new.php?post_type=gatherpress_event',
		preferredVersions: {
			php: phpVersion,
			wp: 'latest',
		},
		phpExtensionBundles: ['kitchen-sink'],
		features: {
			networking: true,
		},
		steps: [
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
				},
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
				zipFile: {
					resource: 'vfs',
					path: '/wordpress/pr/pr.zip',
				},
				extractToPath: '/wordpress/pr',
			},
			{
				step: 'installPlugin',
				pluginData: {
					resource: 'vfs',
					path: '/wordpress/pr/gatherpress.zip',
				},
			},
			{
				step: 'importWxr',
				file: {
					resource: 'url',
					url: 'https://raw.githubusercontent.com/GatherPress/gatherpress-demo-data/main/GatherPress-demo-data-0.33.0.xml',
				},
			},
			/**
			 * Run 'enableMultisite' after the plugin activation!
			 *
			 * There have been some weird errors with this step, when ran after the login.
			 * Having it here at the end -kinda- fixes the problem.
			 * @see https://github.com/GatherPress/gatherpress/issues/950
			 */
			/*
			{
				step: 'enableMultisite',
			},
			*/
		],
	};

	// Apply override if provided
	if (override) {
		const sanitized = sanitizeOverride(override);
		mergeOverride(template, sanitized);
	}

	return JSON.stringify(template);
}

/**
 * Generates preview links for different modes of the WordPress Playground.
 * It constructs URLs with an encoded blueprint that applies plugin changes for testing.
 *
 * @param {string} blueprint - The encoded blueprint JSON string.
 * @param {string} prText - Text describing the pull request (e.g., "for PR#X").
 * @returns {Array} - Array of link objects, each containing a title and URL for a specific Playground mode.
 */
function createPlaygroundLinks( blueprint, prText) {
	const playgrounds = [
		{ name: '**Normal** WordPress Playground ', url: 'https://playground.wordpress.net/#' },
		{ name: '**Seamless** WordPress Playground ', url: 'https://playground.wordpress.net/?mode=seamless#' },
		{ name: 'WordPress Playground **Builder** ', url: 'https://playground.wordpress.net/builder/builder.html#' },
	];

	return playgrounds.map(playground => ({
		title: playground.name + prText,
		url: playground.url + blueprint
	}));
}

/**
 * Main function to create and post a comment on the PR with preview links for different PHP versions.
 * It generates Playground preview URLs for each PHP version specified.
 *
 * @param {object} github - An authenticated GitHub API instance.
 * @param {object} context - The context of the event.
 * @param {number} [prNumberOverride] - Explicit PR number; required when called from a workflow_run
 *   context where context.payload.pull_request is not present.
 * @param {string} [filePath] - Path to the blueprint override file (for testing or
 *   when the PR head is checked out to a non-default location).
 */
async function createPreviewLinksComment(github, context, prNumberOverride, filePath) {
	const prNumber       = prNumberOverride ?? context.payload.pull_request.number;
	const zipArtifactUrl = createBlueprintUrl(context.repo, prNumber);  // URL to the built plugin artifact
	const prText         = `for PR#${prNumber}`;  // Descriptive text for the PR

	// Load per-PR override
	const overridePath = filePath + 'PR-' + prNumber + '-blueprint-override.json';
	const override = loadBlueprintOverride(overridePath);

	// Retrieve PHP versions from environment variable (JSON string)
	const phpVersionsEnv = process.env.PHP_VERSIONS || '["8.4","8.2","7.4"]';  // Default to common versions if not set.
	const phpVersions    = JSON.parse( phpVersionsEnv );  // Parse the JSON string into an array

	// Generate preview links for each PHP version
	let previewLinks = '';
	for (const phpVersion of phpVersions) {
		const blueprint      = encodeURI(
			createBlueprint(context.repo, prNumber, zipArtifactUrl, phpVersion, override)
		);
		const links          = createPlaygroundLinks( blueprint, prText );  // Create preview links
		const versionHeading = `#### PHP Version ${phpVersion}\n`;
		const versionLinks   = links.map(link => `- [${link.title}](${link.url})\n`).join('');
		previewLinks        += `\n${versionHeading}\n${versionLinks}`;
	}

	const overrideNote = override
		? `
> ℹ️ This preview includes a custom blueprint override from <code>${overridePath}</code>.
`
		: `
<details><summary>Customize PR Playground</summary>

To customize the Playground preview for this specific PR, create a file at:

<code>
.github/playground/PR-${prNumber}-blueprint-override.json
</code>

The override is merged into the generated blueprint and can be used to:

- Enable Playground features
- Install additional plugins
- Change site options
- Run setup steps before or after the default blueprint

Example:

<pre>
{
	"$schema": "https://gatherpress.org/playground-preview/pr-override-schema.json",
	"landingPage": "/wp-admin/edit.php?post_type=gatherpress_venue",
	"siteOptions": {
		"show_on_front": "page"
	},
	"features": {
		"networking": true
	},
	"prependSteps": [
		{
			"step": "installPlugin",
			"pluginData": {
				"resource": "wordpress.org/plugins",
				"slug": "simple-location"
			},
			"options": {
				"activate": true
			}
		}
	],
	"appendSteps": [
		{
			"step": "runPHP",
			"code": ""
		}
	]
}
</pre>
</details>
`;

	// The title of the comment and its content, including preview links for all PHP versions.
	const title       = '### Preview changes with Playground';
	const commentBody = `
${overrideNote}

You can preview the recent changes ${prText} with the following PHP versions:
${previewLinks}

[Download <code>.zip</code> with build changes](${zipArtifactUrl})

*Made with 💙 from GatherPress & a little bit of [WordPress Playground](https://wordpress.org/playground/). Changes will not persist between sessions.*
`;

	const repoData = {
		owner: context.repo.owner,
		repo: context.repo.repo,
	};

	// Check if any comments already exists
	const { data: comments } = await github.rest.issues.listComments({
		issue_number: prNumber,
		...repoData,
	});

	const existingComment = comments.find(
		(comment) =>
			comment.user.login === 'github-actions[bot]' &&
			comment.body.startsWith( title )
	);
	const commentObject = {
		body: `${title}\n${commentBody}`,
		...repoData,
	};

	// If an existing comment is found, delete it before creating a new one
	if ( existingComment ) {
		await github.rest.issues.deleteComment({
			comment_id: existingComment.id,
			...commentObject,
		});
	}

	// Create a new comment with preview links
	github.rest.issues.createComment({
		issue_number: prNumber,
		...commentObject,
	});
}

module.exports = createPreviewLinksComment;
