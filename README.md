# DASH-to-HLS-Playback
DASH to HLS Playback converts [DASH](https://en.wikipedia.org/wiki/Dynamic_Adaptive_Streaming_over_HTTP)'s MPD manifests to [HLS](https://en.wikipedia.org/wiki/HTTP_Live_Streaming)'s M3U8 manifests. It works by extracting information from MPD files, creating M3U8 master playlist and media playlists, finally plays out with [hls.js](https://github.com/video-dev/hls.js).

DASH to HLS Playback does not need any player, it works directly on a standard HTML <video> element on a browser HTML5 video and MediaSource Extensions supported.

It is written with Javascript and HTML.

## Getting Worked
### Prerequisite
A web server (for example the localhost) is required to store the newly created M3U8 files, fetch media segments and play them out. Since All HLS resources must be delivered with CORS headers permitting GET requests.
### Installing
Download ZIP from this website. Extracted folder/files should be put under the localhost.
### Running
Use a browser(tested on Chrome and Firefox) to open the extracted folder from server. Provide the MPD URL address or choose from file system the MPD of your interest. Hit Convert and Play.
* If loading MPD from URL, media segments must locate at the same address of the given MPD file.
* If choosing MPD from file system, media segments must be trackable at the root of the running server.
* It only supports MPD file with one period. If several periods in the MPD, it only works on the first period. 

## Bulit With
[hls.js](https://github.com/video-dev/hls.js) - used to stream under HTTP live streaming protocol.
