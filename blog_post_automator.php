<?php
/*
Plugin Name: Blog Post Automator
Description: This plugin scans directories on your server under your blog install and looks for .txt based articles and blog posts. It then allows you to post all of the articles that are in that directory to a specific category you selects with a certain time delay interval that you choose. Auto Blog Builder works great to schedule your future blog posts in a natural drip feed fashion. You may use your own content that you have pre-written or you can use PLR content you own!
Version: 1.6
Plugin URI: http://www.sundaysurprise.com/
Author: Reimund Lube
Author URI: http://www.wednesdaygift.com
*/


    // ---- define some vars ----
    $directories = array();
 
    // ---- add the config page to the plugin menu -----
    add_action('admin_menu', 'addPluginToSubmenu'); 


//----------------------------------------------------------------------------------------------------
// scan for a duplicate post
function check_duplicate($title, $body)
{

    $postslist = get_posts('numberposts=5000000&order=ASC&orderby=title');


    foreach ($postslist as $post)
    {
        if ( ($post->post_title == $title) && ($post->post_content == $body))
        {    return(true);    }
    }


    return(false);
}

//----------------------------------------------------------------------------------------------------
function post_article_post($path, $filename, $when)
{
GLOBAL $warnings;

    // ---- get the article ----
    $article_url = "http://".$_SERVER['HTTP_HOST'].str_replace("wp-admin/plugins.php", "", $_SERVER['SCRIPT_NAME']).$path."/".$filename;
$lines = trim(file_get_contents($article_url));

$rp="`";
$rp2='"';
$rp3="'";

$fd="’";
$fd2="“";
$fd3="‘";

$target = array($fd,$fd2,$fd3);
$replace = array($rp,$rp2,$rp3);

$lines = str_replace($target, $replace, $lines);

//"UTF-8", "ASCII", "Windows-1252", "ISO-8859-15", "ISO-8859-1", "ISO-8859-6", "CP1256"


//echo"article_url=$article_url<br>";

   //$lines = trim(file_get_contents($article_url));
//$lines = implode('', file($article_url));
//$lines=htmlspecialchars($lines, ENT_COMPAT);
echo"lines=$lines";
    $lines = explode("\n", $lines);

    // ---- get title/body ----
    $post_name_out = $lines[0];
    $body_out = "";
    $post_name_out = $lines[0];
    $post_keywds = $lines[1];
    $post_desc = $lines[2];

    for ($i=3; $i<sizeof($lines); $i++)
    {
        $body_out .= $lines[$i]."\n";
    }

    $body = nl2br($body_out);
    $body = str_replace("\n", "", $body);
    $body = str_replace("\r", "", $body);


    // tags
    $tags_input = "";	// $post_name_out;


    // ---- check title/body is not empty (and ignore with warning) ----
    if ( (trim($post_name_out) == "") && (trim($body_out) == "") )
    {
        $warnings .= "WARNING: empty title/body on $filename (file ignored)<br>";
        return(0);
    }


    // ---- see if this post already exists (and ignore with warning) ----
    if ( check_duplicate($post_name_out, $body_out) )
    {
        $warnings .= "WARNING: duplicate post found for article $filename (file ignored)<br>";
        return(0);
    }


    // ---- post the post ----

    $post = array(
        "post_title" => $post_name_out,
        "post_content" => $body_out,
        "post_category" => array($_POST['cat']),
        "post_date" => date("Y-m-d H:i:s", $when),
        "tags_input" => strtolower(str_replace(" ", ", ", $tags_input)),

        "post_status" => "future"
    );



$post_id=wp_insert_post($post);


$meta_key="keywords";
$meta_value =$post_keywds;
add_post_meta($post_id, $meta_key, $meta_value, $unique = false);


$meta_value = $post_desc;
$meta_key="description";
add_post_meta($post_id, $meta_key, $meta_value, $unique = false);

    return(1);
}

//----------------------------------------------------------------------------------------------------
// check valid input / post the articles
function process_articles_post()
{
GLOBAL $warnings;

    $warnings = "";

    $now = $_POST['now'];
    $start = strtotime($_POST['start_year']."/".$_POST['start_month']."/".$_POST['start_day']." ".$_POST['start_hour'].":".$_POST['start_minute']);


    // ---- check for errors ----
    $error = "";

    if ($start < ($now-120))
    {    $error = "Invalid start date/time";    }
    else
    if (!is_numeric($_POST['interval_hours']))
    {    $error = "Interval (hours) must be a number";    }
    else
    if (!is_numeric($_POST['interval_minutes']))
    {    $error = "Interval (minutes) must be a number";    }
    else
    if (!is_numeric($_POST['interval_seconds']))
    {    $error = "Interval (seconds) must be a number";    }
    else
    if (($_POST['interval_hours'] <= 0) && ($_POST['interval_minutes'] <= 0) && ($_POST['interval_seconds'] <= 0) )
    {    $error = "Interval must be greater than 0";    }

    if ($error != "")
    {    return($error);    }
        

    // ---- calculate interval ----

    $i = array("minutes" => "60", "hours" => "3600", "days" => "86400");
    $interval = ($_POST['interval_hours']*3600) + ($_POST['interval_minutes']*60) + ($_POST['interval_seconds']);


    // ---- build list of articles and post ----

    $d = dir("../".$_POST['path']);

    $i = 0;
    $count = 0;
    $filename_array = array();
    $filename_array2 = array();
    
    $successful_posts = 0;
$artcount=0;

//Build array of articles
while (false !== ($entry = $d->read()))
    {
if(stristr($entry, ".txt") ){    
$filename_array2[$artcount]=$entry;
$artcount++;
	                      }
    }
//Sort aticles
sort($filename_array2);

$artcount=0;
$arycount=count($filename_array2);	


while($arycount>$artcount)
   {

$entry=$filename_array2[$artcount];
$artcount++;

        if ( stristr($entry, ".txt") )
           {    
               set_time_limit(59);	// incase there are LOTS of articles

                   $count++;


                   if ($count == 1)
                   {
                       $successful_posts += post_article_post($_POST['path'], $entry, $start+($i*$interval));
                       $count = 0;
                       $i++;	// counts the interval

                   }
           }	// END check extension

    }

    // check for overflow
    if (sizeof($filename_array) != 0)
    {
        post_article_post($_POST['path'], $entry, $start+($i*$interval));
    }

    // carry on!
    $d->close();


    // ---- success ----
    return($successful_posts);
}


//----------------------------------------------------------------------------------------------------
// setup html menu
function init_article_plugin()  
{  
GLOBAL $directories;
GLOBAL $warnings;

    // ---- attempt to process ----
    if (isset($_POST['tfunction']))
    {
        $result = process_articles_post();
    }


    // ---- carry on ----
       
?>
<div class="wrap">

<h2>Blog Post Automator Campaign</h2><br>
<b>Version - 1.6<b><br><br>
<b>Step #1 Directory</b> - Use the directory form below to select the directory/folder<br>
that your blog posts are located in for this campaign.<br><br>

<b>Step #2 Start Time</b> - Select the time that you wish your new blog posts to start<br>
appearing live on your blog.<br><br>

<b>Step #3 Interval </b> - Select the time between posts to show up live on your blog.<br><br>

<b>Step #4 Posting Category</b> - Select the category on your blog that you wish all of <br>
the posts from this campaign to appear in.<br><br>

<b>Step #5 Auto Blog Your Posts</b> - Click "Post Articles" to automatically schedule your <br>
posts to drip feed into your blog for totally automated future posting.<br>
<br>
All articles are added to your future posts queue immediately and scheduled by Wordpress.<br> 
No additional action is required on your part, your blog has now been automated.<br>
<br>

<?php

    // show warnings
    if ($warnings != "")
    {
        echo "<b><i>".$warnings."</i></b><br>";

    }

    // show results
    if (isset($result))
    {
        if (!is_numeric($result))
        {    ?> <font style="color:#ff0000;font-weight:bold;"><?= $result; ?><br><br></font> <?php    }
        else
        {    ?> <font style="color:#0000ff;font-weight:bold;"><?= $result; ?> Articles posted successfully!</font><br>(Do NOT click refresh on your browser)<br><br> <?php    }

    }

?>

<form method="POST" action="<?= $_SERVER["REQUEST_URI"]; ?>">

<input type="hidden" name="tfunction" value="go">
<input type="hidden" name="now" value="<?= date("U"); ?>">
<table cellpadding=6>

<tr>
    <td>Directory</td>
    <td>
        <select name="path">

<?php

    scan_for_directories("../wp-content/plugins/");

    arsort($directories);	// get directories in alphabetical order


    foreach ($directories as $key => $value)
    {
        ?>
            <option value="<?= $key; ?>"><?= $key; ?> (<?= $value; ?> .txt files found)</option>
        <?php
    }
?>

        </select>
    </td>
</tr>

<tr>
    <td>Start time</td>
    <td>
    <select name="start_month">
    <?php
        $months = array("January" => 1, "February" => 2, "March" => 3, "April" => 4, "May" => 5, "June" => 6, "July" => 7, "August" => 8, "September" => 9, "October" => 10, "November" => 11, "December" => 12);

        foreach ($months as $key => $value)
        {
            ?>
            <option value="<?= $value; ?>" <?php if (date("m")==$value) { echo " selected "; } ?>><?= $key; ?></option>
            <?php
        }
        
    ?>
    </select>


<input type="text" size=3 name="start_day" value="<?= date("d"); ?>">,
<input type="text" size=5 name="start_year" value="<?= date("Y"); ?>">
@
<input type="text" size=3 name="start_hour" value="<?= date("H"); ?>">
<input type="text" size=3 name="start_minute" value="<?= date("i"); ?>">

    </td>
</tr>

<tr>
    <td>Interval</td>
    <td>
    <input type="text" name="interval_hours" value="0" size=3 maxlength=2>
        Hours
        &nbsp;
    <input type="text" name="interval_minutes" value="0" size=3 maxlength=2>
        Minutes
        &nbsp;
    <input type="text" name="interval_seconds" value="0" size=3 maxlength=2>
        Seconds
    </td>
</tr>

<tr>
    <td>Posting category</td>
    <td>
        <?php wp_dropdown_categories('show_count=0&hide_empty=0&hierarchical=1'); ?>
    </td>
</tr>


<tr>
    <td colspan=2 align="center">
        <input type="submit" value="Post Articles">
    </td>
</tr>

</table>
</form>

<br>
<iframe src="http://blogpostautomator.com/updates" width=700 height=800 frameborder=0 scrolling=no></iframe>

</div>
<?php
}  

//----------------------------------------------------------------------------------------------------
function scan_for_directories($directory, $filter=FALSE)
{
GLOBAL $directories;

    // if the path has a slash at the end we remove it here
    if(substr($directory,-1) == '/')
    {
        $directory = substr($directory,0,-1);
    }
 
    // if the path is not valid or is not a directory ...
    if(!file_exists($directory) || !is_dir($directory))
    {
        // ... we return false and exit the function
        return FALSE;
 
    // ... else if the path is readable
    }elseif(is_readable($directory))
    {
        // we open the directory
        $directory_list = opendir($directory);
 
        // and scan through the items inside
        while (FALSE !== ($file = readdir($directory_list)))
        {
            // if the filepointer is not the current directory
            // or the parent directory
            if($file != '.' && $file != '..')
            {
                // we build the new path to scan
                $path = $directory.'/'.$file;
 
                // if the path is readable
                if(is_readable($path))
                {
                    // we split the new path by directories
                    $subdirectories = explode('/',$path);
 
                    // if the new path is a directory
                    if(is_dir($path))
                    {
                        // add the directory details to the file list
                        $directory_tree[] = array(
                            'path'    => $path,
                            'name'    => end($subdirectories),
                            'kind'    => 'directory',
 
                            // we scan the new path by calling this function
                            'content' => scan_for_directories($path, $filter));

                        $article_count = get_article_count_from_dir($path);

                        if ($article_count > 0)
                        {    $directories[str_replace("../", "", $path)] = $article_count;    }
 
                    }
                }
            }
        }
        // close the directory
        closedir($directory_list); 
 
        // return file list
        return $directory_tree;
 
    // if the path is not readable ...
    }else{
        // ... we return false
        return FALSE;    
    }
}

//----------------------------------------------------------------------------------------------------
function get_article_count_from_dir($path)
{
    $count = 0;

    // setup dir class instance and go!
    $d = dir($path);

    while (false !== ($entry = $d->read()))
    {
        if ( (stristr($entry, ".txt")) )
           {    $count++;    }
    }

    $d->close();

    return($count);
}

//----------------------------------------------------------------------------------------------------
// internal
function addPluginToSubmenu()   
{  
    add_submenu_page('plugins.php', 'Blog Post Automator', 'Blog Post Automator', 10, __FILE__, 'init_article_plugin');  
}  

//----------------------------------------------------------------------------------------------------

?>