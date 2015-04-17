# hlsinjector
ID3 metadata injector for MPEG TS (HLS) written in PHP

I couldn't find any tool online that could add (inject) ID3 timed metadata into a HLS (basically MPEG TS) stream.
There are Apple tools but they only run under MAC OS and, it appears, they got bugs.

Here you are – not a code worth programming competition, but it's working and it's going to save you hours of googling.

# How to use
You must have PHP CLI installed. Thankfully, it's totally cross-platform and free. Once you have it installed, just run:

`HLS Injector by Denis Mysenko
Syntax: ./injector.php -i filename -m mode [-o filename] [-d]

		-i filename	input file (MPEG TS format)
		-m mode		choose 'analyze' or 'inject'
		-o filename	output filename in case of 'inject' mode
        -e filename with with timed metadata
		-d		    enable debug mode`

# Format of metadata

To be compatible with Apple tools (which are not that popular, though), I decided to keep the same format. Metadata file is
a plain text file with the following format:

`<Moment> <format> <filename>`

Where <moment> is the time point in seconds when the piece of metadata should be shown, <format> is "id3" (it's the only
supported format at the moment) and <filename> is an absolute or relative path to ID3 file containing metadata.

One line for each ID3 file or moment. Lines don't have to be sorted chronologically.

# Demo

You can see it in action on [Rapport](https://www.rapport.fm/en/video) – this is where I needed it, actually.

# License

Use it however you wish.