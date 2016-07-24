<% if $Data() %><% loop $Data() %><% if $Title %>$Title<% else_if $FirstName %>$FirstName $Surname<% end_if %>
<% end_loop %><% end_if %>
