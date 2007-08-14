<?php
/*
Plugin Name: Last.fm Recent Albums and Artwork
Plugin URI: http://remysharp.com/2007/07/26/lastfm-recent-album-artwork-plugin/
Description: Reads the recent songs that you've listened to from Last.fm and pulls album artwork
Version: 1.0.2
License: GPL
Author: Remy Sharp
Author URI: http://remysharp.com
*/

$version = intval(phpversion());

function get_lastFM_album_artwork() {
    global $version;        
    // user specific
    for($i = 0 ; $i < func_num_args(); $i++) {
         $args[] = func_get_arg($i);
    }
    
    $default_html_format = '';

    /*$username = 'remysharp';
    $num_to_show = '6';
    $width = '60';
    $size = 'medium';
    $html_format = '<li><a href="$url">$artwork</a></li>';
    $album_path = '/Users/remy/Sites/remysharp.com/htdocs/albums/';
    $url_prefix = '/albums/';  
    $include_track = '1';*/

    if (!isset($args[0])) $num_to_show = get_option('lastfm_num_to_show'); else $num_to_show = $args[0];
    if (!isset($args[1])) $width = get_option('lastfm_width'); else $width = $args[1];
    if (!isset($args[2])) $size = get_option('lastfm_size'); else $size = $args[4];
    if (!isset($args[3])) $username = get_option('lastfm_username'); else $username = $args[5];
    
    if (!isset($args[4])) $html_format = stripslashes(get_option('lastfm_html_format')); else $html_format = $default_html_format;
    
    $album_path = get_option('lastfm_album_path');
    $url_prefix = get_option('lastfm_url_prefix');
    
    $include_track = get_option('lastfm_include_track');
    
    // code starts
    $perms = fileperms($album_path);
    if (!((($perms & 0x4000) == 0x4000) /* d */ && ($perms & 0x0100) /* r */ && ($perms & 0x0080) /* w */)) {
        echo "Album path must exist and be read/write permissions ($album_path)";
        return;
    }
      
    // general
    $is_loaded = false;
    $lastfm_url = "http://ws.audioscrobbler.com/1.0/user/" . $username . "/recenttracks.xml";
	
	$dom = loaddom($lastfm_url);
	$albums = array(); // set up dummy
	
	if ($dom) {
	    if ($version == 5) {
    		$albums = $dom->getElementsByTagName('track');	        
	    } else {
		    $root = $dom->document_element();
            $albums = $root->get_elements_by_tagname('track');
	    }
	} else {
	    if ($version == 5) {
	        $albums = (object)array('length' => 0);
	    }
	}

    $count = 0;
    $shown = array();

    $album_len = ($version == 5 ? $albums->length : count($albums));

	while ($album_len) {
	    if ($version == 5) {
    		$this_album = $albums->item(0);
    		$this_album->parentNode->removeChild($this_album); // reduces $albums->length    	        
	    } else {
	        $this_album = array_shift($albums);
	    }

		$guid = get_guid($this_album, $include_track);
        // user_error('found: ' . $guid);
		$artwork = get_album_artwork($guid, $album_path, $size);

		if ($guid && !isset($shown[$guid]) && $artwork != '') {
		    $shown[$guid] = true;
		    $count++;
            print_album($this_album, $artwork, $width, $url_prefix, $html_format);
		}

		if ($count == $num_to_show) break;
		$album_len = ($version == 5 ? $albums->length : count($albums));
	}        
	
	if ($count < $num_to_show) {
	    // user_error("Collecting from cache");
	    $files = array();
	    // collect the rest of the images from the cache
	    if ($dh = opendir($album_path)) {
            while (($file = readdir($dh)) !== false) {
                if (substr($file, strrpos($file, '.')+1) == 'jpg' ||
                    substr($file, strrpos($file, '.')+1) == 'jpeg' ||
                    substr($file, strrpos($file, '.')+1) == 'gif' ||
                    substr($file, strrpos($file, '.')+1) == 'png') {
                    $files[filemtime($album_path . $file)] = $file;
                }
            }
        }
        
        if (count($files)) {
            sort($files, SORT_NUMERIC);
            $files = array_reverse($files);
            for ($i = 0; $i < count($files); $i++) {
                $guid = preg_replace('/_/', ' ', preg_replace('/\.jpg$/', '', $files[$i])); // substr($files[$i], strrpos($files[$i], '/') + 1)
                if (!$include_track) {
                    $parts = explode('^', $guid);
                    if (count($parts) == 3) {
                        $guid = $parts[0] . '^' . $parts[1];
                    }
                }
                if (!isset($shown[$guid])) {
                    print_album(null, $files[$i], $width, $url_prefix, $html_format);
                    $count++;

                    if ($count == $num_to_show) break;

                    $shown[$guid] = true;                    
                }
            }
        }
	}
}

function get_guid($a, $include_track) {
    $artist = nodeValue($a, 'artist');
    $album = nodeValue($a, 'album');
    $track = nodeValue($a, 'name');
    
    if ($album && $artist) {
        $album = preg_replace('/\s+\(.*\)/', '', $album); // note: striping anything in brackets as Amazon tends not to have this
        
        $guid = $album . "^" . $artist;
        
        if ($include_track) $guid .= "^$track";
        
        return $guid;
    } else {
        return '';
    } 
}

function nodeValue($context, $n) {
    global $version;
    if ($version == 5) {
        return $context->getElementsByTagName($n)->item(0)->nodeValue;
    } else {
        $nodes = $context->get_elements_by_tagname($n);
        return $nodes[0]->get_content();
    }     
}

function loaddom($url) {
    global $version;
    $dom = null;
    if ($version == 5) {
        $dom = new DOMDocument;
        $ok = @$dom->load($url);
        if ($ok == false) $dom = false;
    } else {
        $dom = @domxml_open_file($url);
    }
    
    return $dom;
}

function get_album_artwork($guid, $album_path, $size) {
    if (!$guid) return '';
    
    global $version;
    
    $guid_image_path = guid_image_path($guid);
    
    if (file_exists($album_path . $guid_image_path)) {
        touch($album_path . $guid_image_path); // used to keep track of recent albums outside of the feed
        return $guid_image_path;
    } else {
        // user_error('trying to find ' . $guid);
        list($album, $artist) = explode('^', $guid);
        $url = 'http://ws.audioscrobbler.com/1.0/album/' . urlencode($artist) . '/' . urlencode($album) . '/info.xml';

        $dom = loaddom($url);
		if ($dom) {
            if ($version == 5) {
                $image_url = $dom->getElementsByTagName('coverart')->item(0)->getElementsByTagName($size)->item(0)->nodeValue;                    
            } else {
                $root = $dom->document_element();
                $nodes = $root->get_elements_by_tagname('coverart');
                $nodes = $nodes[0]->get_elements_by_tagname($size);
                $image_url = $nodes[0]->get_content();
            }                
            
		    $image_data = file_get_contents($image_url);

            // save first
            $fp = fopen($album_path . $guid_image_path, 'w');
            fwrite($fp, $image_data);
            fclose($fp);
            
            return $guid_image_path;
        } else {
            // user_error("Exception on loading artwork from URL ($url)");
	        return '';
	    }
    }
}

function guid_image_path($guid) {
    return preg_replace('/[\?]/', '', preg_replace('/\s/', '_', $guid)) . '.jpg';
}

function print_album($a, $image, $width, $url_prefix, $format) {
    $track = '';
	if ($a) {
		$url = nodeValue($a, 'url');
		$album = nodeValue($a, 'album');
		$artist = nodeValue($a, 'artist');
		$track = nodeValue($a, 'name');
	} else {
	    $file = preg_replace('/_/', ' ', preg_replace('/\.jpg$/', '', substr($image, strrpos($image, '/') + 1)));
	    $parts = explode('^', $file);
	    if (count($parts) == 2) {
	        list($album, $artist) = $parts;
	    } else if (count($parts) == 3) {
	        list($album, $artist, $track) = $parts;
	    } else {
	        $album = $parts[0];
	    }
	}
	
	if (!$album) $album = '_';

    // fake the url
    $url = 'http://www.last.fm/music/' . urlencode($artist) . '/' . urlencode($album) . '/' . urlencode($track);

    $artwork = '<img src="' . $url_prefix . $image .'" height="' . $width . '" width="' . $width . '" alt="Album art for ' . $album . ' by ' . $artist . '" title="' . $artist . ' - ' . $album . '" />';
	
	$html = $format;
	$html = preg_replace('/\$artist/', $artist, $html);
	$html = preg_replace('/\$album/', $album, $html);
	$html = preg_replace('/\$track/', $track, $html);
	$html = preg_replace('/\$url/', $url, $html);
	$html = preg_replace('/\$artwork/', $artwork, $html);
	
    echo $html;
}



function lastfm_recent_albums_subpanel() {
    if (isset($_POST['update_lastfm_recent_albums'])) {
        $option_username = $_POST['username'];
        $option_num_to_show = $_POST['num_to_show'];
        $option_width = $_POST['width'];
        $option_size = $_POST['size'];
        $option_album_path = $_POST['album_path'];
        $option_url_prefix = $_POST['url_prefix'];
        
        $option_html_format = $_POST['html_format'];
        $option_include_track = ($_POST['include_track'] ? '1' : '0');
         
        update_option('lastfm_username', $option_username);
        update_option('lastfm_num_to_show', $option_num_to_show);
        update_option('lastfm_width', $option_width);
        update_option('lastfm_size', $option_size);
        update_option('lastfm_album_path', $option_album_path);
        update_option('lastfm_url_prefix', $option_url_prefix);
        update_option('lastfm_html_format', $option_html_format);
        update_option('lastfm_include_track', $option_include_track);
        
        ?> <div class="updated"><p>Options changes saved.</p></div> <?php
    }
	?>

		<form method="post">

	<div class="wrap">		
		<h2>last.fm Recent Albums Options</h2>
		<fieldset class="options">
		<table>
		 <tr>
		  <td><p><strong><label for="username">Username</label>:</strong></p></td>
	      <td><input name="username" type="text" id="username" value="<?php echo get_option('lastfm_username'); ?>" size="20" /></td>
         </tr>
         
		 <tr>
		  <td><p><strong><label for="num_to_show">Album covers to show</label>:</strong></p></td>
	      <td><input name="num_to_show" type="text" id="num_to_show" value="<?php echo get_option('lastfm_num_to_show'); ?>" size="3" /></td>
         </tr>
         
		 <tr>
		  <td><p><strong><label for="size">Source size of album art</label>:</strong></p></td>
	      <td>
	          <select name="size" id="size">
	              <option <?php if(get_option('lastfm_size') == 'small') { echo 'selected'; } ?> value="small">Small</option>
	              <option <?php if(get_option('lastfm_size') == 'medium') { echo 'selected'; } ?> value="medium">Medium</option>
	              <option <?php if(get_option('lastfm_size') == 'large') { echo 'selected'; } ?> value="large">Large</option>
	          </select>
	          Helps the quality of the image after it is resized to the defined width below.
	      </td>
         </tr>
         
		 <tr>
		  <td><p><strong><label for="width">Width of image</label>:</strong></p></td>
	      <td><input name="width" type="text" id="width" value="<?php echo get_option('lastfm_width'); ?>" size="3" /> numerical width in pixels</td>
         </tr>

         <tr>
            <td><p><strong><label for="html_format">HTML Format</label>:</strong></p></td>
            <td><textarea cols="75" rows="5" name="html_format" id="html_format"><?php echo htmlspecialchars(stripslashes(get_option('lastfm_html_format'))); ?></textarea>
            <p><strong>Variables</strong>: </p>
            <p>$album, $artist, $artwork - the image tab with the artwork, $track - the track name, $url - the Last.fm url to the track (or album)</p>
            </td>
         </tr>
        
        </table>
        </fieldset>
    </div>

	<div class="wrap">
        <h2>Album Art Cache Settings</h2>
        <fieldset class="options">
        <table>
		 <tr>
		  <td><p><strong><label for="url_prefix">URL: </label></strong></p></td>
	      <td><input name="url_prefix" type="text" id="url_prefix" value="<?php echo get_option('lastfm_url_prefix'); ?>" size="50" /> e.g. http://url.com/album_art/</td>
         </tr>
         
		 <tr>
		  <td><p><strong><label for="album_path">Full Path: </label></strong></p></td>
	      <td><input name="album_path" type="text" id="album_path" value="<?php echo get_option('lastfm_album_path'); ?>" size="50" /> e.g. /home/path/to/wp-content/lastfm_album_art/ (ensure the directory is read and writable)</td>
         </tr>

		 <tr>
		  <td><p><strong><label for="include_track">Include Track: </label></strong></p></td>
	      <td><input name="include_track" type="checkbox" id="include_track" <?php echo (get_option('lastfm_include_track') == '1' ? 'checked="checked"' : ''); ?> /> If you include the track name, more images will be created in your directory, but if you don't less recently played music may not be able to show the track name (i.e. we use the image filename to work out track details when there are not enough in the Last.fm feed)</td>
         </tr>
         </table>
        </fieldset>
    </div>

		<p><div class="submit"><input type="submit" name="update_lastfm_recent_albums" value="<?php _e('Update Last.fm Recent Albums', 'update_lastfm_recent_albums') ?>"  style="font-weight:bold;" /></div></p>
        </form>
<?php } // end lastfm_recent_albums_subpanel()

function lra_admin_menu() {
   if (function_exists('add_options_page')) {
        add_options_page('Last.fm Recent Albums Options Page', 'Last.fm Recent Albums', 8, basename(__FILE__), 'lastfm_recent_albums_subpanel');
        }
}

add_action('admin_menu', 'lra_admin_menu'); 
// get_lastFM_album_artwork();
	
?>
