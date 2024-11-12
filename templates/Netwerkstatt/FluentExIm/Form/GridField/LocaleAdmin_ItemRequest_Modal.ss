<div id="$ImportModalID.ATT" class="modal fade grid-field-import" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <% if $ImportModalTitle %>
                    <h2 class="modal-title">$ImportModalTitle</h2>
                <% end_if %>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <% if $ImportForm %>
                    $ImportForm
                <% end_if %>
            </div>
        </div>
    </div>
</div>
