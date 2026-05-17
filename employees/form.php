<?php
$employee = $employee ?? ['employee_no'=>'','first_name'=>'','last_name'=>'','gender'=>'','birthdate'=>'','email'=>'','phone'=>'','hire_date'=>'','department_id'=>'','position_id'=>'','status_id'=>'','address'=>'','monthly_salary'=>''];
$errors = $errors ?? [];
?>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card mb-4">
    <div class="card-header">Basic Personal Details</div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">Employee ID</label><input class="form-control" name="employee_no" required value="<?= e($employee['employee_no']) ?>"></div>
        <div class="col-md-3"><label class="form-label">First Name</label><input class="form-control" name="first_name" required value="<?= e($employee['first_name']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Last Name</label><input class="form-control" name="last_name" required value="<?= e($employee['last_name']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender" required><option value="">Select gender</option><?php foreach (['Female','Male','Other'] as $gender): ?><option value="<?= $gender ?>" <?= $employee['gender'] === $gender ? 'selected' : '' ?>><?= $gender ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Birthdate</label><input class="form-control" type="date" name="birthdate" required value="<?= e($employee['birthdate']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Age</label><input class="form-control" value="<?= e(calculate_age($employee['birthdate'])) ?>" disabled></div>
        <div class="col-md-3"><label class="form-label">Contact Number</label><input class="form-control" name="phone" value="<?= e($employee['phone']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Email Address</label><input class="form-control" type="email" name="email" required value="<?= e($employee['email']) ?>"></div>
        <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?= e($employee['address']) ?></textarea></div>
    </div>
    <div class="card-header border-top">Employment Information</div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">Department</label><select class="form-select" id="department_id" name="department_id" required><option value="">Select department</option><?php foreach ($departments as $department): ?><option value="<?= $department['id'] ?>" <?= (string)$employee['department_id'] === (string)$department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Position</label><select class="form-select" id="position_id" name="position_id" required><option value="">Select department first</option><?php foreach ($positions as $position): ?><option value="<?= $position['id'] ?>" data-department-id="<?= e((string)$position['department_id']) ?>" <?= (string)$employee['position_id'] === (string)$position['id'] ? 'selected' : '' ?>><?= e($position['title']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Employment Status</label><select class="form-select" name="status_id" required><option value="">Select status</option><?php foreach ($statuses as $status): ?><option value="<?= $status['id'] ?>" <?= (string)$employee['status_id'] === (string)$status['id'] ? 'selected' : '' ?>><?= e($status['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Date Hired</label><input class="form-control" type="date" name="hire_date" required value="<?= e($employee['hire_date']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Monthly Salary</label><input class="form-control" type="number" step="0.01" min="0" name="monthly_salary" value="<?= e((string)$employee['monthly_salary']) ?>"></div>
    </div>
    <div class="card-footer form-actions"><a class="btn btn-secondary btn-action" href="index.php"><i class="fas fa-arrow-left"></i><span>Cancel</span></a><button class="btn btn-primary btn-action" type="submit"><i class="fas fa-floppy-disk"></i><span>Save Employee</span></button></div>
</form>
<script>
(() => {
    const departmentSelect = document.getElementById('department_id');
    const positionSelect = document.getElementById('position_id');
    if (!departmentSelect || !positionSelect) return;

    const placeholder = positionSelect.querySelector('option[value=""]');
    const positionOptions = Array.from(positionSelect.querySelectorAll('option[data-department-id]'));

    function filterPositions() {
        const departmentId = departmentSelect.value;
        const selectedOption = positionSelect.selectedOptions[0];
        const selectedMatchesDepartment = selectedOption && selectedOption.dataset.departmentId === departmentId;

        positionOptions.forEach((option) => {
            option.hidden = !departmentId || option.dataset.departmentId !== departmentId;
            option.disabled = !departmentId || option.dataset.departmentId !== departmentId;
        });

        if (placeholder) {
            placeholder.textContent = departmentId ? 'Select position' : 'Select department first';
        }

        if (!selectedMatchesDepartment) {
            positionSelect.value = '';
        }
    }

    departmentSelect.addEventListener('change', filterPositions);
    filterPositions();
})();
</script>
