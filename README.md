PHP-unique-code-generator
=========================

A PHP class, forked from http://code.google.com/p/unique-code-generator/, used to generate random codes, given a character set to choose from, code length, and number of codes to use. It uses a MySQL database to check and store the codes.

Original author: Darren Inwood, Chrometoaster New Media Ltd (lucidtone at gmail.com)

Original source taken from http://code.google.com/p/unique-code-generator/

Primary change is the inclusion of a write buffer that limits the number of calls to count() function. This may create some problems when duplicate codes are found, but this is acceptable for our requirements of creating a fuzzy total amount of cards.
