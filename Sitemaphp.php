<?php

// The source code packaged with this file is Free Software, Copyright (C) 2012 by
// Ãvaro Remesal <contacto at alvaroremesal dot net>.
// It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise.
// You can get copies of the licenses here:
//      http://www.affero.org/oagpl.html
// AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".

/*
 * TODO
 * 
 * - Support for sitemaps indexes
 * - Support for news separate sitemaps
 * - Max number of image items for each URL item
 * - Add more format types for GEO positioning, documentation URL doesn't work (http://support.google.com/webmasters/bin/answer.py?hl=en&answer=94555 -> http://support.google.com/webmasters/bin/answer.py?answer=94556)
 * 
 * Extensions:
 * 
 * - Mobile extension (https://support.google.com/webmasters/bin/answer.py?answer=34627)
 * 
 * Extensions supported:
 * 
 * - Image extension (https://support.google.com/webmasters/bin/answer.py?answer=178636)
 * - Video extension (https://support.google.com/webmasters/bin/answer.py?answer=80471)
 * - GEO extension (https://support.google.com/webmasters/bin/answer.py?answer=94554)
 * - Code search extension (https://support.google.com/webmasters/bin/answer.py?answer=75225)
 * - News extension (http://www.google.com/support/news_pub/bin/answer.py?answer=75717&hl=es | http://www.google.com/support/news_pub/bin/topic.py?topic=11666)
 */

// Uncomment for use with CodeIgniter:
//if (!defined('BASEPATH')) exit('No direct script access allowed');

define('SITEMAP_TYPE_XML', 0);
define('SITEMAP_TYPE_TXT', 1);
define('MAX_IMAGES', 1000);


class SitemapHPException extends Exception
{}

/*
 * class SitemapHP
 * 
 * Validate XML: http://www.validome.org/google/validate
 * 
 */
class SitemapHP
{
    var $file = NULL;
    var $path = NULL;
    var $filepath = NULL;
    var $fileobj = NULL;
    var $type = NULL;
    var $sxe = NULL;
    var $media = array();
    
    
    const SITEMAP_URL_ERROR = 100;
    const SITEMAP_FILETYPE_ERROR = 130;
    const SITEMAP_VIDEOPLAYER_ERROR = 150;
    const SITEMAP_GEOFORMAT_ERROR = 160;
    const SITEMAP_CODESEARCH_ERROR = 170;
    const SITEMAP_FILE_ERROR = 180;
    const SITEMAP_XML_ERROR = 190;
    
    /**
     * Constructor, initializes file, path, type, and populate basic DOM if type is XML
     *   and file is empty (first execution)
     * 
     * @param file String Name of the sitemap file. Defaults to 'sitemap.xml'
     * @param type String Which type of file [txt | sitemap]. Defaults to 'sitemap'
     * @param path String Path for sitemap file. Defaults to DOCUMENT_ROOT
     */
    function __construct($file = "sitemap.xml", $type = SITEMAP_TYPE_XML, $path = '')
    {
        $this->file = $file;
        $this->type = $type;

        $this->path = $path == '' ? $_SERVER['DOCUMENT_ROOT'] : $path;

        $this->filepath = $this->path . '/' . $this->file;

        $file = $this->path . '/' . $this->file;

        // Initialize file
        $this->openfile();

        $isEmptyFile = (filesize($file) == 0);

        // If it's a new sitemap using sitemap protocol (XML file),
        // initialize empty DOM from Sitemap Protocol Standard (https://support.google.com/webmasters/bin/answer.py?hl=es&answer=183668)
        if( $this->type == SITEMAP_TYPE_XML && $isEmptyFile ) {
            $this->initializeXML();
        }

        $this->closefile();
    }
    
    /**
     * Adds a new URL to sitemap
     * 
     * @param String $url URL to be added
     * @param bool $lastmod If true, adds today date as last modified date. Defaults to TRUE
     * @param String $changefreq Change frequency for this URL, must be 'always' or 'hourly' or 'daily' or 'weekly' or 'monthly' or 'yearly' or 'never'. Optional
     * @param String $priority Priority of indexing for this URL, must be between 0.0 and 1.0. Optional
     * @throws SitemapHPException if URL, changefreq or priority are invalid
     * @throws Exception if sitemap type is not set
     */
    public function addURL($url, $lastmod = TRUE, $changefreq = '', $priority = ''){
       
        if( ! $this->is_valid_url($url) ) {
            throw new SitemapHPException("$url is not a valid URL", self::SITEMAP_URL_ERROR);
        }
        
        if( $changefreq != '' && ! $this->is_valid_changefreq($changefreq) ) {
            throw new SitemapHPException("$changefreq is not a valid changefreq ([always|hourly|daily|weekly|monthly|yearly|never])", self::SITEMAP_URL_ERROR);
        }
        
        if( $priority != '' && ! $this->is_valid_priority($priority) ) {
            throw new SitemapHPException("$priority is not a valid priority (0.0 - 1.0)", self::SITEMAP_URL_ERROR);
        }
        
        if( $this->type == SITEMAP_TYPE_XML ) {
         
            $dom = $this->getXML();

            // dom adds a new element under the root
            $urlnode = $dom->appendChild(new DOMElement('url'));

            // dom adds a new element under the url element
            $data = $urlnode->appendChild(new DOMElement('loc'));
            
            // Set content for loc element under our url element
            $data->nodeValue = $url;
            
            if( $lastmod ) {
                $data = $urlnode->appendChild(new DOMElement('lastmod'));    
                $data->nodeValue = date('Y-m-d');
            }
            
            if( $changefreq != '' ) {
                $data = $urlnode->appendChild(new DOMElement('changefreq'));    
                $data->nodeValue = $changefreq;
            }
            
            if( $priority != '' ) {
                $data = $urlnode->appendChild(new DOMElement('priority'));    
                $data->nodeValue = $priority;
            }
            
            $this->addMedia($urlnode);

            $this->updateXML();

        }
        elseif( $this->type == SITEMAP_TYPE_TXT ) {

            $this->openfile();

            fputs($this->fileobj, $url . "\n");
        }
        else {
            throw new Exception('Invalid type: ' . $this->type, self::SITEMAP_FILETYPE_ERROR);
        }

        $this->closefile();
        
        $this->cleanUp();
    }
    
    /*
     * Adds an image to be attached to a URL
     * 
     * @param String $imgurl URL for the image
     * @param String $caption The caption of the image. Optional
     * @param String $geo_location The geographic location of the image. Optional
     * @param String $title The title of the image. Optional
     * @param String $license A URL to the license of the image. Optional
     * @throws SitemapHPException if invalid URL or invalid license, if set
     * @throws Exception if sitemap type is not set
     */
    public function addImg($imgurl, $caption = '', $geo_location = '', $title = '', $license = ''){
        
        if( ! $this->is_valid_url($imgurl) ) {
            throw new SitemapHPException("$imgurl is not a valid image URL", self::SITEMAP_URL_ERROR);
        }

        if( $this->type == SITEMAP_TYPE_XML ) {
         
            // It's mandatory to add namespace, becouse DomDocumentFragment hasn't access
            //  to its parent DomDocument's namespaces declarations. More info: https://bugs.php.net/bug.php?id=44773
              $imgstr = <<<EOD
<image:image xmlns:image="http://www.sitemaps.org/schemas/sitemap-image/1.1">
       <image:loc>$imgurl</image:loc> 
EOD;
              
              if( $caption != '' ) {
                  $imgstr .= <<<EOD
        <image:caption>$caption</image:caption>
EOD;
              }
              
              if( $geo_location != '' ) {
                  $imgstr .= <<<EOD
        <image:geo_location>$geo_location</image:geo_location>
EOD;
              }
              
              if( $title != '' ) {
                  $imgstr .= <<<EOD
        <image:title>$title</image:title>
EOD;
              }
              
              if( $license != '' ) {
                  if( ! $this->is_valid_url($license) ) {
                        throw new SitemapHPException("$license is not a valid URL", self::SITEMAP_URL_ERROR);
                    }
                  $imgstr .= <<<EOD
        <image:license>$license</image:license>
EOD;
              }
              
           $imgstr .= <<<EOD
</image:image>
EOD;
                        
            $this->media[] = $imgstr;


        }
        elseif( $this->type == SITEMAP_TYPE_TXT ) {
            throw new SitemapHPException("Can't add an image into a TXT format sitemap", self::SITEMAP_FILETYPE_ERROR);
        }
        else {
            throw new Exception('Invalid type: ' . $this->type, self::SITEMAP_FILETYPE_ERROR);
        }
    }
    
    /**
     * Adds a video to be attached to a URL
     * 
     * @param String $content_loc Location of the video
     * @param String $player_loc Location of the video player
     * @param String $thumbnail_loc Location of the video thumbnail. Optional
     * @param String $title Title. Optional
     * @param String $description A short description. Optional
     * @param String $player_allow_embed Allow or disallow video embedding. Defaults to 'yes'
     * @param String $player_autoplay Set autoplay. Defaults to 'ap=1' -> Autoplay activated
     * @throws SitemapHPException if invalid URL for content, player or thumbnail, or if invalid allow_embed string or autoplay string
     * @throws Exception if sitemap type is not set
     */
    public function addVideo($content_loc, 
            $player_loc = '', 
            $thumbnail_loc = '', 
            $title = '', 
            $description = '', 
            $player_allow_embed = 'yes', 
            $player_autoplay = 'ap=1'){
        
        if( ! $this->is_valid_url($content_loc) ) {
            throw new SitemapHPException("$content_loc is not a valid video URL", self::SITEMAP_URL_ERROR);
        }
        
        if( $player_loc != '' && ! $this->is_valid_url($player_loc) ) {
            throw new SitemapHPException("$player_loc is not a valid URL", self::SITEMAP_URL_ERROR);
        }
        
        if( $thumbnail_loc != '' && ! $this->is_valid_url($thumbnail_loc) ) {
            throw new SitemapHPException("$thumbnail_loc is not a valid URL", self::SITEMAP_URL_ERROR);
        }
        
        if( ! $this->is_valid_allow_embed($player_allow_embed) ) {
            throw new SitemapHPException("Invalid $player_allow_embed", self::SITEMAP_VIDEOPLAYER_ERROR);
        }
        
        if( ! $this->is_valid_autoplay($player_autoplay) ) {
            throw new SitemapHPException("Invalid $player_autoplay", self::SITEMAP_VIDEOPLAYER_ERROR);
        }

        if( $this->type == SITEMAP_TYPE_XML ) {
         
            // It's mandatory to add namespace, because DomDocumentFragment hasn't access
            //  to its parent DomDocument's namespaces declarations. More info: https://bugs.php.net/bug.php?id=44773
              $videostr = <<<EOD
<video:video xmlns:video="http://www.sitemaps.org/schemas/sitemap-video/1.1">     
      <video:content_loc>http://www.example.com/video123.flv</video:content_loc>
EOD;
              
              if( $thumbnail_loc != '' ) {
                  $videostr .= <<<EOD
      <video:thumbnail_loc>http://www.example.com/thumbs/123.jpg</video:thumbnail_loc>
EOD;
              }
              
              if( $player_loc != '' ) {
                  $videostr .= <<<EOD
       <video:player_loc allow_embed="$player_allow_embed" autoplay="$player_autoplay">
         $player_loc
       </video:player_loc>
EOD;
              }
              
              if( $title != '' ) {
                  $videostr .= <<<EOD
        <video:title>$title</video:title>  
EOD;
              }
              
              if( $description != '' ) {
                  $videostr .= <<<EOD
        <video:description>$description</video:description>
EOD;
              }
          
          $videostr .= <<<EOD
    </video:video>
EOD;
                        
            $this->media[] = $videostr;


        }
        elseif( $this->type == SITEMAP_TYPE_TXT ) {
            throw new SitemapHPException("Can't add a video into a TXT format sitemap", self::SITEMAP_FILETYPE_ERROR);
        }
        else {
            throw new Exception('Invalid type: ' . $this->type, self::SITEMAP_FILETYPE_ERROR);
        }
    }
    
    /**
     * Adds geo positioning info to the URL
     * 
     * @param String $format Case-insensitive. Specifies the format of the geo content. Examples include "kml" and "georss". Defaults to 'kml'.
     * @throws SitemapHPException if invalid format.  Suported formats: http://support.google.com/webmasters/bin/answer.py?answer=94556
     * @throws SitemapHPException if updating a TXT format sitemap
     * @throws Exception if sitemap type is not set
     */
    public function addIsGeo($format = 'kml'){
        
        if( ! $this->is_valid_geoformat($format) ) {
            throw new SitemapHPException("$format is not a valid GEO format", self::SITEMAP_GEOFORMAT_ERROR);
        }

        if( $this->type == SITEMAP_TYPE_XML ) {
         
            // It's mandatory to add namespace, becouse DomDocumentFragment hasn't access
            //  to its parent DomDocument's namespaces declarations. More info: https://bugs.php.net/bug.php?id=44773
              $imgstr = <<<EOD
<geo:geo xmlns:geo="http://www.google.com/geo/schemas/sitemap/1.0">            
       <geo:format>$format</geo:format>
</geo:geo>
EOD;
                        
            $this->media[] = $imgstr;


        }
        elseif( $this->type == SITEMAP_TYPE_TXT ) {
            throw new SitemapHPException("Can't add geo positioning into a TXT format sitemap", self::SITEMAP_FILETYPE_ERROR);
        }
        else {
            throw new Exception('Invalid type: ' . $this->type, self::SITEMAP_FILETYPE_ERROR);
        }
    }
    
    /**
     * Adds Google's Code Search to helps users find function definitions and sample code by enabling them to search publicly accessible source code hosted on the Internet.
     * 
     * @param String $filetype Case-insensitive. The value "archive" indicates that the file is an archive file. For source code files, the value defines the the source code language. Examples include "C", "Python", "C#", "Java", "Vim". For source code language, the Short Name, as specified in the list of supported languages, must be used. The value must be printable ASCII characters, and no white space is allowed.
     * Only supported languages will be indexed. If the language of your code is not yet supported, you can still submit the Sitemap and Google may index your code in the future.
     * Supported languages: https://support.google.com/webmasters/bin/answer.py?hl=en&answer=75252
     * @param String $license Optional. Case-insensitive. The name of the software license. For archive files, this indicates the default license for files in the archive. Examples include "GPL", "BSD", "Python", "disclaimer". You must use the Short Name, as specified in the list of supported licenses.
     * When the value is not one of the recognized licenses, this will cause us to index the item as "unknown license".
     * Supported licenses: https://support.google.com/webmasters/bin/answer.py?answer=75256
     * @param String $filename Optional. The name of the actual file. This is useful if the URL ends in something like download.php?id=1234 instead of the actual filename. The name can contain any character except "/". If the file is an archive file, it will be indexed only if it has one of the supported archive suffixes.
     * Supported suffixes: https://support.google.com/webmasters/bin/answer.py?answer=75259
     * @param String $packageurl Optional. For use only when the value of codesearch:filetype is not "archive". The URL truncated at the top-level directory for the package. For example, the file http://path/Foo/1.23/bar/file.c could have the packageurl http://path/Foo/1.23. All files in a package should have the same packageurl. This tells us which files belong together.
     * @param String $packagemap Optional. Case-sensitive. For use only when codesearch:filetype is "archive". The name of the packagemap file inside the archive. Just like a Sitemap is a list of files on a web site, a packagemap is a list of files in a package. 
     * Packagemap definition: http://www.google.com/help/codesearch_packagemap.html
     * @throws SitemapHPException if updating a TXT format sitemap
     * @throws Exception if sitemap type is not set
     */
    public function addCodeSearch($filetype, $license = '', $filename = '', $packageurl = '', $packagemap = ''){
        
        if( $filetype == '' ) {
            throw new SitemapHPException("Filetype required", self::SITEMAP_CODESEARCH_ERROR);
        }

        if( $this->type == SITEMAP_TYPE_XML ) {
         
            // It's mandatory to add namespace, becouse DomDocumentFragment hasn't access
            //  to its parent DomDocument's namespaces declarations. More info: https://bugs.php.net/bug.php?id=44773
              $codestr = <<<EOD
<codesearch:codesearch xmlns:codesearch="http://www.google.com/codesearch/schemas/sitemap/1.0">
       <codesearch:filetype>$filetype</codesearch:filetype>
EOD;
              if( $license != '') {
                  $codestr .= <<<EOD
       <codesearch:license>$license</codesearch:license>
EOD;
              }
              
              if( $filename != '') {
                  $codestr .= <<<EOD
       <codesearch:filename>$filename</codesearch:filename>
EOD;
              }
              
              if( $packageurl != '') {
                  if( $filetype == 'archive') {
                      throw new SitemapHPException("Can't use PackageURL with filetype 'archive'", self::SITEMAP_CODESEARCH_ERROR);
                  }
                  $codestr .= <<<EOD
       <codesearch:packageurl>$packageurl</codesearch:packageurl>
EOD;
              }
               
              if( $packagemap != '') {
                  if( $filetype != 'archive') {
                      throw new SitemapHPException("Can't use PackageMap with other filetype than 'archive'", self::SITEMAP_CODESEARCH_ERROR);
                  }
                  $codestr .= <<<EOD
       <codesearch:packagemap>$packagemap</codesearch:packagemap>
EOD;
              }
              
                $codestr .= <<<EOD
</codesearch:codesearch>
EOD;
                        
            $this->media[] = $codestr;


        }
        elseif( $this->type == SITEMAP_TYPE_TXT ) {
            throw new SitemapHPException("Can't add code search information into a TXT format sitemap", self::SITEMAP_FILETYPE_ERROR);
        }
        else {
            throw new Exception('Invalid type: ' . $this->type, self::SITEMAP_FILETYPE_ERROR);
        }
    }
    
    /**
     * Adds mobile tag, for generate Mobile Sitempas (http://support.google.com/webmasters/bin/answer.py?hl=en&answer=34648)
     * Important note: A Mobile Sitemap can contain only URLs that serve mobile web content. Any URLs that serve only non-mobile web content will be ignored by the Google crawling mechanisms. If you have non-mobile content, create a separate Sitemap for those URLs.
     * 
     * @throws SitemapHPException if updating a TXT format sitemap
     * @throws Exception if sitemap type is not set
     */
    public function addMobile(){
        
        if( $this->type == SITEMAP_TYPE_XML ) {
         
            // It's mandatory to add namespace, becouse DomDocumentFragment hasn't access
            //  to its parent DomDocument's namespaces declarations. More info: https://bugs.php.net/bug.php?id=44773
              $imgstr = <<<EOD
<mobile:mobile xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0" />
EOD;
                        
            $this->media[] = $imgstr;


        }
        elseif( $this->type == SITEMAP_TYPE_TXT ) {
            throw new SitemapHPException("Can't add mobile tag into a TXT format sitemap", self::SITEMAP_FILETYPE_ERROR);
        }
        else {
            throw new Exception('Invalid type: ' . $this->type, self::SITEMAP_FILETYPE_ERROR);
        }
    }
    
    
    /********** Private methods **********/
    
    

    /* openFile
     * 
     * @param mode String Mode to open sitemap file (PHP File Modes). Defaults to 'a+'
     * 
     */
    
    /**
     * Opens stream into sitemap file
     * 
     * @param String $mode Open mode, is a PHP fileopen mode
     * @throws SitemapHPException if can't open file
     */
    private function openfile($mode = 'a+') {

        try {
             $this->fileobj = fopen($this->filepath, $mode);

             if( $this->fileobj == NULL ) {
                 throw new SitemapHPException("Can't open file: $this->filepath", self::SITEMAP_FILE_ERROR);
             }
        }
        catch (Exception $e) {
            die($e->getMessage().'<pre>'.$e->getTraceAsString().'</pre>');
        }
    }
    
    /**
     * Closes sitemap file stream
     *  
     */
    private function closefile() {
        fclose($this->fileobj);
    }

    /** 
     * Generates an empty XML DOM according to Sitemap Protocol Standard (https://support.google.com/webmasters/bin/answer.py?hl=es&answer=183668)
     *     and writes it to file
     * 
     */
    private function initializeXML() {
        $str = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.sitemaps.org/schemas/sitemap-image/1.1"
        xmlns:video="http://www.sitemaps.org/schemas/sitemap-video/1.1">
</urlset>
EOD;

        fputs($this->fileobj, $str);
        
        return;
    }

    /**
     * Validates an URL
     * 
     * @param String $url URL to validate
     * @return bool 
     */
    private function is_valid_url( $url ) {
        return preg_match( '/http[s]?\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/', $url );
    }
    
    /**
     * Validate a changefreq, must be 'always' or 'hourly' or 'daily' or 'weekly' or 'monthly' or 'yearly' or 'never'
     * 
     * @param String $changefreq
     * @return bool 
     */
    private function is_valid_changefreq( $changefreq ) {
        $values = array('always','hourly','daily','weekly','monthly','yearly','never');
        
        return in_array($changefreq, $values);
        
    }
    
    /**
     * Validate a priority, must be between 0.0 and 1.0
     * 
     * @param String $priority
     * @return bool 
     */
    private function is_valid_priority( $priority ) {
               
        return preg_match('/[0,1].[0-9]/', $priority);
        
    }
    
    /**
     * Validate allow_embed param for video item
     * 
     * @param String $str
     * @return bool 
     */
    private function is_valid_allow_embed($str) {
        return ( $str == 'yes' || $str == 'no');
    }
    
    /**
     * Validate autoplay param for video item
     * 
     * @param String $str
     * @return bool 
     */
    private function is_valid_autoplay($str) {
        return ( $str == 'ap=1' || $str == 'ap=0');
    }
    
    /**
     * Validate format of geo information
     * 
     * @param String $str Format of geo information
     * @return bool 
     */
    private function is_valid_geoformat($str) {
        return ( $str == 'kml' || $str == 'georss');
    }
    
    /**
     * Imports XML from file, and loads its DOM
     * 
     * @return DomDocument
     * @throws SitemapHPException if can't parse the XML document
     */
    private function getXML() {
        $this->sxe = simplexml_load_file($this->filepath);
            
        if ($this->sxe === false) {
            throw new SitemapHPException('Error while parsing the document' . $this->filepath, self::SITEMAP_XML_ERROR);
        }

        // get a dom interface on the simplexml object
        $dom = dom_import_simplexml($this->sxe);
        
        return $dom;
    }
    
    /**
     * Adds a new media (image or video) to actual URL
     * 
     * @param DomElement $node DOM Element of the actual URL, new media will be inserted as a child of this element
     */
    private function addMedia($node) {
        foreach( $this->media as $media ) {
               
            $fragment = $node->ownerDocument->createDocumentFragment();
            $fragment->appendXML($media);

            $node->ownerDocument->importNode( $fragment );
            $node->appendChild( $fragment );
        }
    }
    
    /**
     * Updates XML file with new contents 
     */
    private function updateXML() {
        /****************** DEBUG
        echo ('<pre>');
        print_r($this->sxe);
        echo ('</pre>');
        ****************** /DEBUG */

        // Overwrite file with new contents
        $this->openfile('w');

        fwrite($this->fileobj, $this->sxe->asXML());
    }
    
    /**
     * Cleans up data for reusing of the same object 
     */
    private function cleanUp() {
        $this->sxe = NULL;
        $this->media = array();
    }

}

/********************************** Testing ***********************************************

try {
$a = new SitemapHP('sitemap.xml', SITEMAP_TYPE_XML, system('pwd'));

echo "Add new URL\n";
$a->addURL('http://www.example.com');

echo "Add new URL with extra data\n";
$a->addURL('http://www.example2.com', TRUE, 'weekly', '0.3');

echo "Add new URL with image\n";
$a->addIMG('http://www.image.com/myimage.jpg');
$a->addURL('http://www.example3.com');

echo "Add new URL with two images with extra data and GEO information\n";
$a->addImg('http://www.image.com/myimage2.jpg', 'Image caption', 'Limerick, Ireland', 'Image title', 'http://creativecommons.org');
$a->addImg('http://www.image.com/myimage3.jpg', 'Other image caption', 'Chicago, Illinois', 'Other image title', 'http://creativecommons.org');
$a->addIsGeo('kml');
$a->addURL('http://www.example4.com');

echo "Add new URL with video\n";
$a->addVideo('http://www.myvideo.com?id_video=999', 'http://www.player.com/player.swf?video=999', 
        'http://www.myvideo.com?thumb=999', 'My video', 'My description');
$a->addURL('http://www.example5.com');

echo "Add new URL with Code Search\n";
$a->addCodeSearch('PHP', 'GPL', 'filename.php', 'http://path/Foo/1.23');
$a->addURL('http://www.example6.com');

echo "Add new URL for Mobile Sitemap\n";
$a->addMobile();
$a->addURL('http://www.example7.com');


// Force errors 
echo "Add new invalid URL\n";
//$a->addURL('NotAnURL');
echo "Add new invalid changefreq\n";
$a->addURL('NotAChangfreq');
echo "Add new invalid priority\n";
//$a->addURL('NotAPriority');
}
catch (SitemapHPException $e) {
    echo 'An error has occured while creating Sitemap File: ',  $e->getMessage(), "\n";
}
catch (Exception $e) {
    echo 'An error has occured: ',  $e->getMessage(), "\n";
}

********************************** /Testing ***********************************************/
