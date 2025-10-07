function printEmployeeReport() {
  const printContent = document.getElementById('printArea').innerHTML;
  const originalContent = document.body.innerHTML;

  document.body.innerHTML = printContent;
  window.print();
  document.body.innerHTML = originalContent;

  location.reload();
}
