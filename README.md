# hlsinjector
ID3 metadata injector for MPEG TS (HLS) written in PHP

I couldn't find any tool online that could add (inject) ID3 timed metadata into a HLS (basically MPEG TS) stream.
There are Apple tools but they only run under MAC OS and, it appears, they got bugs.

Here you are – not a code worth programming competition, but it's working and it's going to save you hours of googling.

# How to use
You must have PHP CLI installed. Thankfully, it's totally cross-platform and free. Once you have it installed, just run:

`HLS Injector by Denis Mysenko
Syntax: ./injector.php -i filename -m mode [-o filename] [-d]

		-i filename	    input file (MPEG TS format)
		-m mode		    choose 'analyze' or 'inject'
		-o filename	    output filename in case of 'inject' mode
        -e filename     with with timed metadata
        --metastart N   start CC for metadata stream from this number
		-d		        enable debug mode`

# Work modes

You can run this tool in either analyze or injection mode. Analyze mode allows to verify compliance of your streams with
the standards. It will check all headers, continuity counters, CRC checksums, etc. If you use analyze mode with debug
key – you will see all important internals of the TS, including PAT and PMT tables.

Injection mode allows you to insert ID3 metadata into existing MPEG TS file (stream). Based on metadata file (see below)
this tool will add extra frames at specific points of your original TS. Moreover, PMT of your main program will be
modified – we will add extra descriptors and one extra elementary stream/PID for metadata. By default, it will be
assigned a number next after your highest existing PID (eg. if your TS file's last PID was 0x102, metadata stream
will be on PID 0x103).

# Format of metadata

To be compatible with Apple tools (which are not that popular, though), I decided to keep the same format. Metadata file is
a plain text file with the following format:

`<Moment> <format> <filename | tag>`

Where <moment> is the time point in seconds when the piece of metadata should be shown, <format> is either "id3" or
"plaintext". In case of "id3", a filename of the ID3 file must follow. If case of "plaintext", a plain text
ID3 title should follow (will be inserted as TPE1 title).

One line for each ID3 file or moment. Lines don't have to be sorted chronologically.

# Demo

You can see it in action on [Rapport](https://www.rapport.fm/en/video) – this is where I needed it, actually.

# License

Use it however you wish.