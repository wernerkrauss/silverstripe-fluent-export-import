<h2>Translation Log:</h2>

<% loop $Status %>
    <h3>$i18n_singular_name: $Title - (#$ID)</h3>

    <ul>
        <% loop getLocalesTranslatedToForTemplate %>
            <li>$Locale: $Status</li>
        <% end_loop %>
    </ul>

<% end_loop %>

