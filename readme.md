# Network Media Library

Network Media Library is a plugin for WordPress Multisite which provides a central media library that's shared across all sites on the Multisite network.

## Description

This small plugin transparently shares media from one central media library site to all the other sites on the network. All media that's uploaded gets transparently directed to the central media site, and subsequently made available network-wide. Nothing is copied, cloned, synchronised, or mirrored, so for each file that's uploaded there's only one attachment and one copy of the file.

## Minimum Requirements ##

**PHP:** 7.0  
**WordPress:** 4.9  

## Installation

The plugin is available as a [Composer package](https://packagist.org/packages/humanmade/network-media-library).

    composer require humanmade/network-media-library

If you don't use Composer, install the plugin as you would normally.

The plugin should either be installed as a mu-plugin or network activated. It's a network plugin and therefore cannot be activated on individual sites on the network.

Site ID `2` is used by default as the central media library. You should configure your media library site ID via the filter hook `network-media-library/site_id`:

```php
add_filter( 'network-media-library/site_id', function( $site_id ) {
    return 123;
} );
```

## Usage

Use the media library on the sites on your network just as you would normally. All media will be transparently stored on and served from the chosen central media library site.

Attachments can be deleted only from within the admin area of the central media library.

## Compatibility

Network Media Library works transparently and seamlessly with all built-in WordPress media functionality, including uploading files, cropping images, inserting media into posts, and viewing attachments. Its functionality works with the site icon, site logo, background and header images, featured images, galleries, the audio and image widgets, and regular media management.

The plugin works with the block editor, the classic editor, the REST API, XML-RPC, and all standard Ajax endpoints for media management.

Links to media from other sites mostly work, although there are a couple of edge case bugs in WordPress core that need to be fixed (I'll get to these soon).

Compatibility with third-party plugins is good, but not guaranteed. The following plugins and libraries are explicitly supported by Network Media Library:

* [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/)
* [Regenerate Thumbnails](https://wordpress.org/plugins/regenerate-thumbnails/)
* [WP User Avatars](https://wordpress.org/plugins/wp-user-avatars/)

The following plugins and libraries have been tested and confirmed as compatible out of the box:

* [BuddyPress](https://wordpress.org/plugins/buddypress/)
* [Extended CPTs](https://github.com/johnbillion/extended-cpts)
* [Gutenberg](https://wordpress.org/plugins/gutenberg/)
* [Stream](https://wordpress.org/plugins/stream/)
* [User Profile Picture](https://wordpress.org/plugins/metronet-profile-picture/)

I plan to fully test (and add support if necessary) many other plugins and libraries, including CMB2, Fieldmanager, and many gallery and media management plugins. Stay tuned for updates!

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
