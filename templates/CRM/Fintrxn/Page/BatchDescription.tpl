{literal}
  <script>
    cj( document ).ready(function() {
      cj("th").each(function() {
        if (this.classList.contains("crm-batch-payment_instrument")) {
          this.innerHTML = "Batch Description";
          this.className = "crm-batch-description";
        }
      });
      cj("table").each(function() {
        if ( cj.fn.dataTable.fnIsDataTable(this) ) {
          var myTableId = this.id;
          var myTable = cj("#"+myTableId).dataTable();
          myTable.on( "draw.dt", function () {
            cj("#"+myTableId+" td").each(function() {
              var batchId = cj(this).parent().attr("data-id");
              if (this.className === " crm-batch-payment_instrument") {
                var batchTitleCell = this;
                CRM.api3("Batch", "getvalue", {return:"description", id:batchId})
                  .done(function(data) {
                    batchTitleCell.innerHTML = data.result.substring(0,80);
                  });
                this.className = " crm-batch-description";
              }
            });
          });
        };
      });
    });
  </script>
{/literal}



