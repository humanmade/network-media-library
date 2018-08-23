# Network Media Library

Network Media Library is a plugin for WordPress Multisite which provides a central media library that's shared across all sites on the Multisite network.

## Description

This small plugin transparently shares media from one central media library site to all the other sites on the network. All media that's uploaded gets transparently directed to the central media site, and subsequently made available network-wide.

Site ID `2` is used by default as the central media library. You can configure the media library site ID via the filter hook `network-media-library/site_id`:

```php
add_filter( 'network-media-library/site_id', function( $site_id ) {
    return 123;
} );
```

## Minimum Requirements ##

**PHP:** 7.0  
**WordPress:** 4.9  

## Installation

The plugin is available as a [Composer package](https://packagist.org/packages/johnbillion/network-media-library).

    composer require johnbillion/network-media-library

If you don't wish to use Composer, install the plugin as you would normally.

The plugin should either be installed as a mu-plugin, or network activated. It cannot be activated on individual sites on the network.

## Usage

Use the media library on the sites on your network just as you would normally. All media will be transparently stored on and served from the chosen central media library site.

Currently, users will need to be added to the central media site with sufficient permissions (namely the `upload_files` capability, and the ability to edit attachments). This can be achieved by adding them as an Author level user or higher. Functionality on the Media site will be restricted to only the management of media and nothing else (except for Super Admins).

A future version of this plugin will hopefully remove the need for users to be added to the central media library site.

## Compatibility

Network Media Library works transparently and seamlessly with all built-in WordPress media functionality, including uploading files, cropping images, inserting into posts, and viewing attachments. Its functionality works with the site icon, site logo, background and header images, featured images, galleries, the audio and image widgets, and regular media management.

Some functionality, such as editing or deleting attachments, can only be performed from the admin area of the central media library. Links to media from other sites mostly work, although there are a couple of edge case bugs in WordPress core that need to be fixed (I'll get to these soon).

Compatibility with third-party plugins is good, but not guaranteed. The following plugins are explicitly supported by Network Media Library:

* [Regenerate Thumbnails](https://wordpress.org/plugins/regenerate-thumbnails/)
* [WP User Avatars](https://wordpress.org/plugins/wp-user-avatars/)

The following plugins and libraries have been tested and confirmed as compatible out of the box:

* [BuddyPress](https://wordpress.org/plugins/buddypress/)
* [Extended CPTs](https://github.com/johnbillion/extended-cpts)
* [Gutenberg](https://wordpress.org/plugins/gutenberg/)
* [Stream](https://wordpress.org/plugins/stream/)
* [User Profile Picture](https://wordpress.org/plugins/metronet-profile-picture/)

I plan to fully test (and add support if necessary) many other plugins and libraries, including CMB2, ACF, and many gallery and media management plugins. Stay tuned for updates!

## Screenshots

There are no screenshots to show as Network Media Library operates transparently and introduces no new UI. Simply upload, manage, insert, and use your media as you would normally, and everything will operate through the central media library.

## License

Good news, this plugin is free for everyone! Since it's released under the MIT, you can use it free of charge on your personal or commercial site.

## History

This plugin originally started life as a fork of the [Multisite Global Media plugin](https://github.com/bueltge/multisite-global-media) by Frank Bültge and Dominik Schilling at [Inpsyde](https://inpsyde.com/), but has since diverged entirely and retains little of the original functionality.

The initial fork of this plugin was made as part of a client project at [Human Made](https://humanmade.com/). We build and manage high-performance WordPress websites for some of the largest publishers in the world.

Hurrah for open source!

## Alternatives

If the Network Media Library plugin doesn't suit your needs, try these alternatives:

* [Multisite Global Media](https://github.com/bueltge/multisite-global-media)
* [Network Shared Media](https://wordpress.org/plugins/network-shared-media/)
