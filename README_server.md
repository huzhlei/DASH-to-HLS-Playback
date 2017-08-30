# DASH-to-HLS-Playback
## Manifest conversion on server side
This branch of DASH to HLS Playback implements MPD to M3U8 manifests conversion on server side.

It composes two parts - manifest conversion with conversion.php on server side and playback with [hls.js](https://github.com/video-dev/hls.js) on client side.

### Conversion on server
MPD to M3U8 conversion and M3U8s generation are both implemented on the server identified by ```Server URL```.

Conversion.php loads MPD files from user's input ```MPD URL```, which can either be example MPDs chosen from the datalist or typed directly by the user. One basic rule is that the media segments are prepared at the same address with the loaded MPD file.

Conversion is triggered when the button ```Convert``` works, it also saves "master.m3u8" and all other necessary media playlists on the specified server.

### Playback on client
On clicking the button ```Play```, "master.m3u8" is passed to ```StreamURL``` and playback is enabled with [hls.js](https://github.com/video-dev/hls.js).
