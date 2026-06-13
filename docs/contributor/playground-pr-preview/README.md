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

