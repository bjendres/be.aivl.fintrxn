<div class="crm-content-block crm-block">
  <div id="help">
    {ts}The results of the validation of the transactions in the batch. The errors are listed and you can edit the
    related contribution to fix the error. A batch can NOT be exported until all errors are fixed!{/ts}
  </div>
  {if empty($batchErrors)}
    <div class="messages status no-popup">
      No errors found, batch can be exported.
    </div>
  {else}
    <div id="validateBatchWrapper" class="dataTables_wrapper">
      <table id="validateBatchTable" class=" display dataTable">
        <thead>
        <tr role="row">
          <th>{ts}Contribution ID{/ts}</th>
          <th>{ts}Contact ID{/ts}</th>
          <th>{ts}Contact Name{/ts}</th>
          <th>{ts}Campaign{/ts}</th>
          <th>{ts}Amount{/ts}</th>
          <th>{ts}Transaction Date{/ts}</th>
          <th>{ts}From Account{/ts}</th>
          <th>{ts}To Account{/ts}</th>
          <th>{ts}Error{/ts}</th>
          <th id="nosort"></th>
        </tr>
        </thead>
        <tbody>
        {assign var="rowClass" value="odd-row"}
        {assign var="rowCount" value=0}
        {foreach from=$batchErrors key=batchErrorId item=batchError}
          {assign var="rowCount" value=$rowCount+1}
          <tr role = "row" id="row{$rowCount}" class="{cycle values="odd,even"}">
            <td>{$batchError.contribution_id}</td>
            <td>{$batchError.contact_id}</td>
            <td>{$batchError.contact_name}</td>
            <td>{$batchError.campaign}</td>
            <td>{$batchError.total_amount|crmMoney}</td>
            <td>{$batchError.transaction_date|crmDate}</td>
            <td>{$batchError.from_account}</td>
            <td>{$batchError.to_account}</td>
            <td>{$batchError.error_message}</td>
            <td>
                <span>
                  {foreach from=$batchError.actions item=actionLink}
                    {$actionLink}
                  {/foreach}
                </span>
            </td>
          </tr>
          {if $rowClass eq "odd-row"}
            {assign var="rowClass" value="even-row"}
          {else}
            {assign var="rowClass" value="odd-row"}
          {/if}
        {/foreach}
        </tbody>
      </table>
    </div>
  {/if}
</div>