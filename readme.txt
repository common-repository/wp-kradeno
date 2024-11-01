=== Plugin Name ===
Contributors: boyan.yurukov
Donate link: https://support.creativecommons.org/donate
Tags: blog, repost, license, steal, post, search, stolen, text, google
Requires at least: 2.8.0
Tested up to: 3.2.1
Stable tag: 1.0

Searches the web for sites that have reposted your blog posts. 

== Description ==

This plugin extracts several sentences from each of your posts and searches in Google for pages that contain them. Then it cross-references the results and suggests sites that may have reposted your texts without permission or backlink.

In the "Posts->WP Kradeno Reports" page you could run a search for reposts. This check takes a lot of time, so you may want to leave it like that. Depending on your server, you may notice a slowed down loading of your site during the check, but that goes only for you - your visitors won't notice any change. If you run out of Google Search calls (4000 per hour is a safe limit), the check will stop and you may start the re-post check in an hour and it will begin from where it stopped.

On the same page you will also see the suggestions ordered by blog posts. Since some sites are known rss agregates or forums, you could pick them out and exclude them from future searches. Furthermore, you could ignore other sites after you make sure they have not copied from you. Apart from the link to the remote site and the Google cache, you will also see the title of the remote site.

Each site has a status. Currently only "ignore" and "warning". make sense. The rest will hide the site from the panel the same way as "ignore" does, but are there for future use. "Warning" serves as a reminder for when you have warned the admin of the remote site to remove the reposted text.

In the Settings page you can set a bigger Google Search calls limit (not recommended), remove/add excluded websites and set a minimum score for the found sites.

== Installation ==

1. Upload `wp-kradeno` to the `/wp-content/plugins/` folder
2. Active the from the 'Plugins' page in WordPress
3. Open the WP Kradeno Reports page and start a re-post check
4. When it's finished, reload the Reports page

== Frequently Asked Questions ==

= Why not make the search for repost run each time I open the admin panel? =

The Google search of 10 sentanses for hundreds of blog posts is quite heavy and time consuming. If it were run when you opened your blog, when it would be almost impossible to use. (by you. Running the script does not affect other visitors)

= Why not make it run on a server? =

I'm working on that.

= Why not make it as a cron job? =

In the next version.

= The plugin found some sites. Can I sue? =

Are you american? No, the plugin makes an assumtions based on several common sentanses. A similar technique is used in the music industry to find similar songs (damn YouTube). That is how the sites get rated. A higher score may mean a repost, but also that both of you quoted someone or that you have the same wordchoice. 

= How do I make them remove my post from their website? =

Find the author/admin email and write them a polite message. If that doesn't work, write an angry one and CC the hosting company. Make sure you give links you both posts and to your license page. Often that won't work as well, but that's a human problem, not a software one.

= I don't have a license page. =

Then anyone can quote, copy, repost and steal you content. If you don't care, plugin is not for you. I think that it's always good to add one and to make it as little restrictive as possible. I recommend Creative Commons Attribution-Share Alike http://creativecommons.org/licenses/by-sa/2.5/

= I start the test, but nothing happens even after 10 mins. =
Please run open this page "http://your-blog-location/wp-content/plugins/wp-kradeno/wpkr-search.php", save it as a file and send it to me (yurukov at gmail.com) I'll look into it. Although this plugin should not interfere with any other plugins, you never know. Often heavily customized WPs (like that of my blog) do not behave as the official version.

A reported problem may be the configuration of php on your server or the blog. Since the searching script runs for quite a while, it uses a lot of memory, so if you have a problem, try increasing the momory limits in php.ini and wp-settings.

= How can I use this on sites other than blogs and on other CMS-s? =
You could copy my code (with CC) and use it on your site. 90% of it is in the functions in wpkr-search.php. The rest is extraxting the text of the articles/sites.

= What does "Kradeno" means? =
"Stollen" in Bulgarian.

== Screenshots ==

1. Some results
2. Alert in the Dashboard
3. Settings panel

== Changelog ==

= 1.0 =
* Fixes in translation and views
* Added "ignore posts" option
* Added option to check posts only in the past 3 months
* Added a settings when to alert on the dashboard that reposts should be checked

= 0.7 =
* Important security fix (Thanks to ___Jul___@Twitter)

= 0.6 =
* Mostly bug fixes

= 0.5 =
* Removed the need of a cron job
* Added a loading bar style re-post check with stats

= 0.2 =
* Searching script
* Admin panel
* Report page
* Dashboard alert



