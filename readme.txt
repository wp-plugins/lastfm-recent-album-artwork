=== Last.fm Recent Album Artwork ===
Contributors: remysharp
Donate link: http://remysharp.com/2007/07/26/lastfm-recent-album-artwork-plugin/
Tags: sidebar, artwork, images, lastfm
Requires at least: 2.0.2
Tested up to: 2.2
Stable tag: 1.0.2

Displays the album cover artwork for recently listened to songs via Last.fm

== Description ==

Displays the album cover artwork for recently listened to songs via [Last.fm](http://last.fm).  

You are required to have a Last.fm account, and you'll to be running the iScrobbler application to automatically notify Last.fm of your songs.

== Installation ==

1. Install the [iScrobbler](http://www.last.fm/group/iScrobbler).  Once this is installed, check the [recent tracks feed](http://ws.audioscrobbler.com/1.0/user/remysharp/recenttracks.xml) (changing *remysharp* to your own username) to ensure your submissions are work.
2. Upload `lastfm_albums_artwork.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Place `<?php get_lastFM_album_artwork(); ?>` in your sidebar template where you want the artwork to appear.

== Frequently Asked Questions ==

= What dependancies are there? =

1. A [Last.fm](http://last.fm) account.
2. [iScrobbler](http://www.last.fm/group/iScrobbler) to tell Last.fm what tracks you are listening to.

= Can I change the way the artwork is laid out? =

Yes.  From WordPress admin, Options, Last.fm Recent Albums: change the layout using the 'HTML format' option.  Valid variables include:

* $album
* $artist
* $artwork - the image tab with the artwork
* $track - the track name
* $url - the Last.fm url to the track (or album).

= Where can I get further help? =

If you contact me on my [personal plugin page](http://remysharp.com/2007/07/26/lastfm-recent-album-artwork-plugin/) I'll get in touch to help resolve any problems you're having.
