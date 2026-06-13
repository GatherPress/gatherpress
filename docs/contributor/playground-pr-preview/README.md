# Playground PR previews

GatherPress PRs have Playground powered previews available in 3 PHP version, and prepared as normal, seamless and builder-enabled Playgrounds. The prepared instances are provided as a comment on each PR, that gets updated automatically.

A PR preview Playground contains the latest changes from this PR, built, installed and activated. The setup gets seeded with the regular gatherpress-demo-data.

## Customize your PR Playground

GatherPress allows to customize the generated Playground for your PR.

You can change the landing page, set options, do stuff before GatherPress is loaded, do stuff afterwards. Everything you can with a regular Playground blueprint. GatherPress loads its important blueprint steps together with yours, resulting in a nicely, reproducible and highly customizable setup.

To customize the Playground preview for a specific PR, create a file at:

<code>.github/playground/PR-{NUMBER_OF_THE_PR}-blueprint-override.json</code>

The override is merged into the generated default blueprint and as such still contains the different php version, GatherPress plugin and demo-data, but also allows to

- Change the landing page
- Enable Playground features
- Change site options
- Run steps before GatherPress' default steps
- Run steps after GatherPress' default steps

###


1.
	<details><summary><strong>Debug with <em>Query Monitor</em></strong></summary>

	```json
	{
		"prependSteps": [
		{
			"step": "installPlugin",
			"pluginData": { "resource": "wordpress.org/plugins", "slug": "query-monitor" },
			"options": { "activate": true }
		}
		]
	}
	```

	</details>

2.
	<details><summary><strong>Test <code>/ical</code>endpoints with <em>Monkeyman Rewrite Analyzer</em></strong></summary>

	```json
	{
		"landingPage": "/wp-admin/admin.php?page=monkeyman-rewrite-analyzer",
		"prependSteps": [
		{
			"step": "installPlugin",
			"pluginData": { "resource": "wordpress.org/plugins", "slug": "monkeyman-rewrite-analyzer" },
			"options": { "activate": true }
		}
		]
	}
	```

	</details>

3.
	<details><summary><strong>Full example</strong></summary>

	```json
	{
		"$schema": "https://gatherpress.org/playground-preview/pr-override-schema.json",
		"landingPage": "/wp-admin/edit.php?post_type=gatherpress_event",
		"siteOptions": {
			"show_on_front": "page"
		},
		"features": {
			"networking": true
		},
		"prependSteps": [
			{
			"step": "installPlugin",
			"pluginData": { "resource": "wordpress.org/plugins", "slug": "gutenberg" },
			"options": { "activate": true }
			}
		],
		"appendSteps": [
			{
			"step": "runPHP",
			"code": "<?php require '/wordpress/wp-load.php'; update_option('gatherpress_some_setting', 'value'); ?>"
			}
		]
	}
	```

	</details>



### Validation Schema

Protected fields cannot be overridden, `preferredVersions` (the PHP version matrix), `phpExtensionBundles`, and raw `step`s are rejected. PR authors must use `prependSteps` and `appendSteps`.

To help with the override style, GatherPress published its own Playground Override Scheme under https://gatherpress.org/playground-preview/pr-override-schema.json.

This should be referenced in a `PR-*-blueprint-override.json` like so:

```json
{
  "$schema": "https://gatherpress.org/playground-preview/pr-override-schema.json",
  [...]
}
```
Editors like *VS Code* will provide autocompletion for all standard and our custom Playground properties with full step-type validation.
