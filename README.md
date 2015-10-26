# French-voters
French voters allows to map the voting method choice of French voters abroad by just uploading the Register of voters with their mode of participation (or non participation).

## Steps
- gather register of voters in electronic form, it should contain the address of all voters. Add the mode of participation to the csv file. The mode of participation is available from a copy of the register used at the polling station and mentions "VE" for electronic voting, "signed" for paper ballot and nothing in case the elector abstained.
- Upload the file on the webapp.
- The file is parsed an addresses are turned in geolocation coordonates.
- The map is generated from the voting method and their coordonates.
