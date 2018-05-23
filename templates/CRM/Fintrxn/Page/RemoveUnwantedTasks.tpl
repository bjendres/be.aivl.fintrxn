{literal}
  <script>
    cj("#batch_update option").each(function() {
      if (cj(this).val() === 'export' || cj(this).val() === 'delete') {
        cj(this).remove();
      }
    });
  </script>
{/literal}