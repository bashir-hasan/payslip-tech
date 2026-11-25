<?php $sample = "Employee ID,Name,Designation,Department,Email,Phone,Joining date,Payment method,Bank,Account no,Gross salary,Tax,PF,Bonus,Overtime,Unpaid days\nEMP-001,Rahim Uddin,Accountant,Finance,rahim@example.com,+8801700000000,2024-01-15,bank,BRAC Bank,1501203344556,40000,0,1200,,,\nEMP-002,Nusrat Jahan,Sales Executive,Sales,,+8801800000000,2025-03-01,bkash,bKash,01800000000,30000,0,900,2000,,1"; ?>
<div class="page-head"><div><a class="small" href="<?= e(url('/app/employees')) ?>">← <?= e(t('Employees')) ?></a><h1 style="margin-top:6px"><?= e(t('Smart import')) ?></h1><p><?= e(t('Upload any Excel (.xlsx) or CSV file: an employee list, a salary sheet or an attendance/bonus sheet. Columns are recognised automatically and you can check them before importing.')) ?></p></div></div>
<div class="grid grid-2" style="align-items:start">
  <div class="card"><div class="card-body">
    <form method="post" action="<?= e(url('/app/employees/import')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field"><label class="label"><?= e(t('Excel or CSV file')) ?></label><input class="input" type="file" name="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required style="padding-top:9px"><span class="hint"><?= e(t('Max 3 MB, up to 1,000 rows. Headers in English or Bangla are fine.')) ?></span></div>
      <button class="btn btn-primary"><?= icon('upload') ?> <?= e(t('Upload and match columns')) ?></button>
      <a class="btn btn-light" download="payslip-import-template.csv" href="data:text/csv;charset=utf-8,<?= rawurlencode($sample) ?>"><?= icon('download') ?> <?= e(t('Example file')) ?></a>
    </form>
  </div></div>
  <div class="card"><div class="card-body">
    <div class="fieldset-title"><?= e(t('What it understands')) ?></div>
    <ul class="small muted" style="padding-left:18px;margin:0;display:grid;gap:6px">
      <li><?= e(t('Employee details: ID, name, designation, department, email, phone, joining date, payment method, bank and account.')) ?></li>
      <li><?= e(t('Salary: a single "Gross / Salary" column (split automatically) or separate basic, housing, medical, transport, tax and PF columns.')) ?></li>
      <li><?= e(t('This month: bonus, overtime, advance and unpaid days are added to your current draft payroll.')) ?></li>
      <li><?= e(t('Existing employees are matched by ID or exact name and updated.')) ?></li>
      <?php if ($cfk = array_column(custom_fields(), 'label')): ?><li><?= e(t('Custom fields:')) ?> <?= e(implode(', ', $cfk)) ?></li><?php endif; ?>
    </ul>
  </div></div>
</div>
