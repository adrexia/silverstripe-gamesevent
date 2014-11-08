<% if $getGroupedGames() %>
<div class="$Name genre-add hide"><% loop $getGroupedGames(Genre).GroupedBy(Genre) %><% loop $Children %><% if $getGenresList() %><% loop $getGenresList() %><% if $Title %>{$Title.LowerCase},<% end_if %><% end_loop %><% end_if %><% end_loop %><% end_loop %></div>
<% end_if %>
