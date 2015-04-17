# hlsinjector
ID3 metadata injector for MPEG TS (HLS) written in PHP

I couldn't find any tool online that could add (inject) ID3 timed metadata into a HLS (basically MPEG TS) stream.
There are Apple tools but they only run under MAC OS and, it appears, they got bugs.

Here you are – not a code worth programming competition, but it's working and it's going to save you hours of googling.

# How to use
`You must have PHP CLI installed. Thankfully, it's totally cross-platform and free. Once you have it installed, just run:

HLS Injector by Denis Mysenko
Syntax: ./injector.php -i filename -m mode [-o filename] [-d]

		-i filename	input file (MPEG TS format)
		-m mode		choose 'analyze' or 'inject'
		-o filename	output filename in case of 'inject' mode
		-d		enable debug mode
