# Multisite Global Media
_Multisite Global Media_ is a WordPress plugin, that shared media across the Multisite network.

## Description
 This simple plugin add a new tab to the media modal and give you the chance to use media from one blog of the network in each blog - shared media across the Multisite network. This is a simple plugin without custom media modal and fixed value for the blog, there store the media for the network. The ID of the blog was set in the constant `blog_id`, currently the blog id `3`. Change this value, if you will use a other side of the network as global media library.
 
To get Global Media to work one has to follow these steps:
1. Decide on which blog/site that will host shared media for the network.
2. Add media to the media library for the specific blog/site.
4. Find the ID of a site by going to All Sites section hovering over the site checking the left bottom status bar left or installing a plugin that shows the ID's of each site.
3. Open the file multisite-global-media.php go to the following code:
 
 ```php
 /**
  * Id of side inside the network, there store the global media
  *
  * @var    integer
  * @since  2015-01-22
  */
 const blog_id = 3;
 ```

### Screenshots
 ![Media Modal](./assets/screenshot-1.png)

## Other Notes

### Crafted by [Inpsyde](http://inpsyde.com) &middot; Engineering the web since 2006.
Yes, we also run that [marketplace for premium WordPress plugins and themes](http://marketpress.com).

### Bugs, technical hints or contribute
Please give me feedback, contribute and file technical bugs on this 
[GitHub Repo](https://github.com/bueltge/Multisite-Global-Media/issues), use Issues.

### License
Good news, this plugin is free for everyone! Since it's released under the GPL, 
you can use it free of charge on your personal or commercial blog.

### Contact & Feedback
The plugin is designed and developed by team members from the [Inpsyde](http://inpsyde.com/) crew.
Special thanks and praise to Dominik Schilling for his quick help.

Please let me know if you like the plugin or you hate it or whatever ... 
Please fork it, add an issue for ideas and bugs.

### Disclaimer
I'm German and my English might be gruesome here and there. 
So please be patient with me and let me know of typos or grammatical parts. Thanks
