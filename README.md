# GatherPress

## 🎖️ The Goal

GatherPress is the result of the WordPress community's desire for new event management tools that meet the diverse needs of event organizers and members.

## 📃 The Project
This project is for the collaborative effort to build a compelling event management application using open source tools such as _WordPress_ and _BuddyPress_ and the grit sweat and love of **the community, for the community**.

We're creating the very network features we need to host events and gather well.

### 🤝 How to Get Involved
If you wish to share in the collaborative of work to build _GatherPress_, please drop us a line either via WordPress Slack, or message us directly on our site [https://gatherpress.org/get-involved](htps://gatherpress.org/get-involved)

### 🔑 Collaborator Access

**GitHub Administrators**
> [Mervin Hernandez](https://github.com/MervinHernandez) and [Mike Auteri](https://github.com/mauteri)

**GatherePress.org**
> Talk to Mervin for access to `gatherpress.org` via SSH and WP Admin login.

# Credits
[mauteri](https://profiles.wordpress.org/mauteri/), [hrmervin](https://profiles.wordpress.org/hrmervin/), [pbrocks](https://profiles.wordpress.org/pbrocks/), [jmarx](https://profiles.wordpress.org/jmarx/), [hauvong](https://profiles.wordpress.org/hauvong/)

---

# [WIP] Documentation Outline

## 1. Setup
1. Download a ZIP of the repository
2. Install it in your WordPress instance
3. Activate the plugin

## 2. Create an Event
1. Go to the WP Admin > Events
2. Add an event
3. Populate a date/time
4. Add the `Attendance Selector` block where you wish to display the CTA for this event
5. Add the `Attendance List` block (if desired) to display the list of attendees
6. Done

## 3. Settings
To find what global configuration changes are available in this plugin go to WP Admin > Settings > GatherPress.


# Developer Documentation

## .wp-env

If you have Docker installed, you could use wp-env package to load a WordPress development environment with this plugin automatically activated.

### To setup this repo for local dev

#### Fork this repository

Although you can download a zip file of the plugin at:

```
https://github.com/GatherPress/gatherpress
```

If you want to help out with development, we suggest forking the code to your own Github repository and creating a branch from there.

#### Clone this repository

Once you've forked the repo, you should now have a mirrored copy of GatherPress, but on your profile's URL, or something like this:

```
https://github.com/YourGithubUsername/gatherpress
```

where `YourGithubUsername` corresponds to your login name for Github.

To clone a local copy, open a terminal window and run the following command:

```sh
git clone git@github.com:YourGithubUsername/gatherpress.git
```

if you have your SSH keys set up. If not, run:

```sh
git clone https://github.com/YourGithubUsername/gatherpress.git
```

##### Note about customizing the URL

Once you have forked the GatherPress repo, you can also change the folder name of your version of the repository by going into your settings of your repo on Github's website.

```
https://github.com/pbrocks/gatherpress
```

![PBrocks GatherPress repo](docs/media/pbrocks-gatherpress.png)

### Install wp-env globally

In a terminal window, run:

```sh
npm i -g @wordpress/env
```

#### Change directory and run wp-env

In your terminal window, run:

```sh
cd gatherpress
wp-env start
```

You should then see that a development site has been configured for you on localhost port 2003

![Development Site Login](docs/media/wp-env.json-startup.png)

#### Log in to Site / Log into Site

![Development Site Login](docs/media/dev-login-gatherpress.png)

#### Development Site Plugins/Themes

To further customize the development site using your favorite or most familiar development plugins or themes, you are able to add whatever you like because of this code added to the `.wp-env.json` file:

```json
 "mappings": {
    "wp-content/plugins": "./wp-core/plugins",
    "wp-content/themes": "./wp-core/themes"
 },
 ```

In fact, after the initial setup, you may notice that in your code editor, there is now a `wp-core` folder containing the default plugins and themes, but it is grayed out, so the contents of this folder will not be committed to the GatherPress repository.

![Development Site Plugins/Themes](docs/media/gitignore—gatherpress.png)

#### To shut down your development session

Simply run:

```sh
wp-env stop
```

For more info on wp-env package, consult the [Block Handbook's page](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).
