# Sleeper Matchup Display for Info Board

This is a simple matchup display written in PHP using SQLite that utilizes the Sleeper API to grab information based on the configured league_id.  It will display the current week's games in a table, and the overall wins / losses for the league in another table.

This was meant to be used in an iframe for display on an informational screen.  I use Dakboard, and it works well with that.

## Setup

 - Open matchups.php and change the $leagueid variable at the top to your league id.
 - Modify the seasonstart variable at the top to a date of the season starting week.  Use the day of the week you want the matchups to update.  For instance, I want my past weeks matchups to display until Wednesday before changing to the new matchups so everyone has time to see how the matchups ended.  So I set it to 9/8/21 because it is the Wednesday of week 1.
 
## Screen Example

![Example of screen](/screenshots/screenshot.png)