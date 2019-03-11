# flidado

Flicker Data Downloader - quick (& probably dirty) tool for downloading all
photos from all sets of single (authenticated) user on Flickr

Written in PHP with use of OAuth library, intended to run on a local computer
as a CLI script, not on a web server. 

At first run it authenticates user on Flickr using the standard OAuth procedure
mentioned in Flick API documentation, with the exception, that no callback
URL is used (again - this is not supposed to be running on a web server),
instead "oob" (out-of-band) method is used, requiring user to copy/paste
verification code from website to the script when asked. After the script gets
access token/secret from server, both are stored to the same directory as
the script in oauth.conf and used ever after.

The script itself is quite heavily commented (at least for my code), so
just quick explanation of what it does:

1. Gets User ID of currently authenticated user.
2. Gets list of all photosets (albums) of that user.
3. For every photoset a local directory is created (funny characters from
album name are removed).
4. Goes through all photos from all these sets.
5. For every photo gets list of all available sizes.
6. Downloads the first photo size from $pref_sizes array (from left to right, so
it's a good idea to put "Original" on the last place, because there's always
an original of the photo). The name of the local photo file is either it's
Flickr title or date taken (when photo has no title; again no funny
characters), extension is used from the Flickr photo URL.

Notice: You are _required_ to edit first three PHP code lines - you need to specify
your timezone and provide API token/secret. You can get these on Flickr by registering
your app and Flickr API won't talk to you without them.