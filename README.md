SitemapHP
=========

PHP Class to help to generate Sitemap files

SitemapHP helps you to dynamically generate Sitemap files for better SEO. With this library you can:

- Create a Sitemap file (various types supported)
- Add URLs to file
- Add multimedia to URL (images and/or videos) according to Google Sitemap specification
- Mark a URL as a Geopositioning one
- Adds Google's Code Search
- Add the ^mobile^ tag according to Google Sitemap specification

For the moment, SitemapHP can generate XML files or TXT files. When generating XML files it uses the [Sitemap Protocol Standard](https://support.google.com/webmasters/bin/answer.py?hl=es&answer=183668).

Using
=====

To use the library, just copy it to the third party libraries directory of your project, instantiate an object and use it. At first execution the library will check if a sitemap file already exists, and crate it if not; then, you can add URLs as you need.

At bottom of the code you will find a example of use, just uncomment it and execute the standalone PHP (`php -e Sitemaphp.php`) to check how it works. This example works only on Linux/*nix, due to use of `system('pwd')`, if you're under Windows just change it with the path where the library is.

Example
-------

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


        /* Force errors */
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

Licensing
=========

The source code of SitemapHP is Free Software, Copyright (C) 2012 by Alvaro Remesal <contacto at alvaroremesal dot net>. It's licensed under the AFFERO GENERAL PUBLIC LICENSE unless stated otherwise. You can get copies of the licenses here: http://www.affero.org/oagpl.html.AFFERO GENERAL PUBLIC LICENSE is also included in the file called "COPYING".
