(() => {
  'use strict';

  const API_BASE = '/customers';

  const VALIDATORS = {
    account_number: {
      test: (v) => /^\d{10,16}$/.test(v),
      message: 'Must be 10–16 digits.',
    },
    customer_name: {
      test: (v) => /^[a-zA-Z\s]+$/.test(v.trim()) && v.trim().length > 0,
      message: 'Letters and spaces only.',
    },
    customer_dob: {
      test: (v) => {
        if (!v) return false;
        const dob = new Date(v);
        if (Number.isNaN(dob.getTime())) return false;
        const age = (Date.now() - dob.getTime()) / (1000 * 60 * 60 * 24 * 365.25);
        return age >= 18;
      },
      message: 'Customer must be at least 18.',
    },
    customer_address: {
      test: (v) => v.trim().length >= 10,
      message: 'At least 10 characters.',
    },
    customer_phone_number: {
      test: (v) => /^\d{10}$/.test(v),
      message: 'Must be exactly 10 digits.',
    },
    loan_type: {
      test: (v) => v.length > 0,
      message: 'Loan type is required.',
    },
    loan_amount: {
      test: (v) => Number(v) >= 10000,
      message: 'Loan amount must be at least ₹10,000.',
    },
    loan_tenure: {
      test: (v) => Number(v) >= 6 && Number(v) <= 60,
      message: 'Loan tenure must be 6–60 months.',
    },
  };

  const CUSTOMER_FIELDS = ['account_number', 'customer_name', 'customer_dob', 'customer_address', 'customer_phone_number'];
  const LOAN_FIELDS = ['loan_type', 'loan_amount', 'loan_tenure'];

  const LOAN_LABELS = {
    'Personal Loan': 'Personal',
    'Home Loan': 'Home',
    'Car Loan': 'Car',
    'Education Loan': 'Education',
  };

  const el = (id) => document.getElementById(id);

  const modeBanner = el('modeBanner');
  const modeLabel = el('modeLabel');
  const cancelModeBtn = el('cancelModeBtn');

  const customerForm = el('customerForm');
  const submitBtn = el('submitBtn');
  const resetBtn = el('resetBtn');
  const formMsg = el('formMsg');
  const loanFieldset = el('loanFieldset');
  const loanSectionHeading = el('loanSectionHeading');

  const searchInput = el('searchInput');
  const refreshBtn = el('refreshBtn');
  const tableBody = el('tableBody');
  const customerCount = el('customerCount');

  let customers = [];

  // formMode: 'create' | 'edit' | 'apply'
  let formMode = 'create';
  let activeAccount = null; // account being edited / applied-to

  // which account rows currently show loan checkboxes, and which loan_ids are checked
  let deleteSelectMode = new Set();
  const selectedLoanIds = new Map(); // account_number -> Set(loan_id)

  // ---------- helpers ----------

  function money(n) {
    const num = Number(n) || 0;
    return '₹' + num.toLocaleString('en-IN', { maximumFractionDigits: 2 });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showFormMsg(text, kind) {
    // kind: 'ok' | 'err' | 'info'
    formMsg.textContent = text;
    formMsg.className = 'form-msg show ' + kind;
  }

  function clearFormMsg() {
    formMsg.className = 'form-msg';
    formMsg.textContent = '';
  }

  async function api(path, options = {}) {
    const res = await fetch(API_BASE + path, {
      headers: { 'Content-Type': 'application/json' },
      ...options,
    });

    let body = null;
    try { body = await res.json(); } catch (_) { /* no body */ }

    if (!res.ok) {
      const message = (body && body.message) || `Request failed (${res.status})`;
      const err = new Error(message);
      err.status = res.status;
      throw err;
    }

    return body;
  }

  function findCustomer(accountNumber) {
    return customers.find((c) => c.account_number === accountNumber) || null;
  }

  // ---------- load + render table ----------

  async function loadCustomers() {
    tableBody.innerHTML = '<tr class="empty-row"><td colspan="8">Loading customers…</td></tr>';
    try {
      const body = await api('');
      customers = (body && body.data) || [];
      renderTable();
    } catch (err) {
      tableBody.innerHTML = `<tr class="empty-row"><td colspan="8">Could not load customers: ${escapeHtml(err.message)}</td></tr>`;
      customers = [];
      customerCount.textContent = '0';
    }
  }

  function renderTable() {
    const query = searchInput.value.trim();
    const filtered = query
      ? customers.filter((c) => c.account_number.includes(query))
      : customers;

    customerCount.textContent = customers.length;
    tableBody.innerHTML = '';

    if (filtered.length === 0) {
      tableBody.innerHTML = `<tr class="empty-row"><td colspan="8">${query ? `No account matches "${escapeHtml(query)}".` : 'No customers on record yet.'}</td></tr>`;
      return;
    }

    filtered.forEach((c) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="cust-id" data-label="Cust. ID">${escapeHtml(c.customer_id)}</td>
        <td class="account" data-label="Account No.">${escapeHtml(c.account_number)}</td>
        <td class="name" data-label="Name">${escapeHtml(c.customer_name)}</td>
        <td data-label="DOB">${escapeHtml(c.customer_dob)}</td>
        <td data-label="Phone">${escapeHtml(c.customer_phone_number)}</td>
        <td data-label="Address">${escapeHtml(c.customer_address)}</td>
        <td data-label="Loans">${buildLoanCell(c)}</td>
        <td data-label="Actions">${buildActionsCell(c)}</td>
      `;
      tableBody.appendChild(tr);
    });

    wireRowEvents();
  }

  function buildLoanCell(customer) {
    const loans = customer.loans || [];
    const account = customer.account_number;

    if (loans.length === 0) {
      return '<span class="null-loans">NULL</span>';
    }

    const inSelectMode = deleteSelectMode.has(account);
    const checkedSet = selectedLoanIds.get(account) || new Set();

    const rows = loans.map((l) => `
      <tr>
        ${inSelectMode ? `<td class="select-col"><input type="checkbox" data-loan-checkbox data-account="${escapeHtml(account)}" data-loan-id="${l.loan_id}" ${checkedSet.has(l.loan_id) ? 'checked' : ''}></td>` : ''}
        <td class="loan-type-cell">${escapeHtml(LOAN_LABELS[l.loan_type] || l.loan_type)}</td>
        <td>${money(l.loan_amount)}</td>
        <td>${escapeHtml(l.loan_tenure)} mo</td>
        <td>${money(l.monthly_emi)}</td>
        <td>${money(l.total_interest)}</td>
        <td>${money(l.total_repayment)}</td>
      </tr>
    `).join('');

    const table = `
      <table class="loan-subtable">
        <thead>
          <tr>
            ${inSelectMode ? '<th class="select-col"></th>' : ''}
            <th>Type</th><th>Amount</th><th>Tenure</th><th>EMI</th><th>Interest</th><th>Repayment</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    `;

    const deleteBar = inSelectMode ? `
      <div class="loan-delete-bar">
        <button type="button" class="btn-danger btn-xs" data-confirm-delete-loans="${escapeHtml(account)}">Confirm Delete Selected</button>
        <button type="button" class="btn-ghost btn-xs" data-cancel-delete-loans="${escapeHtml(account)}">Cancel</button>
      </div>
    ` : '';

    return table + deleteBar;
  }

  function buildActionsCell(customer) {
    const account = customer.account_number;
    const hasLoans = (customer.loans || []).length > 0;

    return `
      <div class="row-actions">
        <button type="button" class="btn-ghost btn-sm" data-update="${escapeHtml(account)}">Update Customer</button>
        <button type="button" class="btn-accent btn-sm" data-apply-loan="${escapeHtml(account)}">Apply New Loan</button>
        <button type="button" class="btn-ghost btn-sm" data-toggle-delete-loans="${escapeHtml(account)}" ${hasLoans ? '' : 'disabled title="No loans to delete"'}>Delete Loan</button>
        <button type="button" class="btn-danger btn-sm" data-delete-customer="${escapeHtml(account)}" title="${hasLoans ? 'Loans must be removed first' : 'Delete this customer'}">Delete Customer</button>
      </div>
    `;
  }

  function wireRowEvents() {
    tableBody.querySelectorAll('[data-update]').forEach((btn) => {
      btn.addEventListener('click', () => enterEditMode(btn.getAttribute('data-update')));
    });

    tableBody.querySelectorAll('[data-apply-loan]').forEach((btn) => {
      btn.addEventListener('click', () => enterApplyLoanMode(btn.getAttribute('data-apply-loan')));
    });

    tableBody.querySelectorAll('[data-delete-customer]').forEach((btn) => {
      btn.addEventListener('click', () => deleteCustomer(btn.getAttribute('data-delete-customer')));
    });

    tableBody.querySelectorAll('[data-toggle-delete-loans]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const account = btn.getAttribute('data-toggle-delete-loans');
        deleteSelectMode.add(account);
        selectedLoanIds.set(account, new Set());
        renderTable();
      });
    });

    tableBody.querySelectorAll('[data-cancel-delete-loans]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const account = btn.getAttribute('data-cancel-delete-loans');
        deleteSelectMode.delete(account);
        selectedLoanIds.delete(account);
        renderTable();
      });
    });

    tableBody.querySelectorAll('[data-confirm-delete-loans]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const account = btn.getAttribute('data-confirm-delete-loans');
        confirmDeleteLoans(account);
      });
    });

    tableBody.querySelectorAll('[data-loan-checkbox]').forEach((cb) => {
      cb.addEventListener('change', () => {
        const account = cb.getAttribute('data-account');
        const loanId = Number(cb.getAttribute('data-loan-id'));
        const set = selectedLoanIds.get(account) || new Set();
        if (cb.checked) set.add(loanId); else set.delete(loanId);
        selectedLoanIds.set(account, set);
      });
    });
  }

  async function confirmDeleteLoans(account) {
    const set = selectedLoanIds.get(account) || new Set();
    if (set.size === 0) {
      showFormMsg('Select at least one loan to delete.', 'err');
      return;
    }

    const loanIds = Array.from(set);
    if (!confirm(`Delete ${loanIds.length} selected loan(s) for account ${account}? This cannot be undone.`)) return;

    try {
      await api(`/${encodeURIComponent(account)}/loans`, {
        method: 'DELETE',
        body: JSON.stringify({ loan_ids: loanIds }),
      });
      showFormMsg(`${loanIds.length === 1 ? 'Loan' : 'Loans'} deleted successfully.`, 'ok');
      deleteSelectMode.delete(account);
      selectedLoanIds.delete(account);
      await loadCustomers();
    } catch (err) {
      showFormMsg(err.message || 'Could not delete selected loan(s).', 'err');
    }
  }

  async function deleteCustomer(account) {
    const c = findCustomer(account);
    const label = c ? `${c.customer_name} (${account})` : account;

    if (!confirm(`Delete customer ${label}? This cannot be undone.`)) return;

    try {
      await api(`/${encodeURIComponent(account)}`, { method: 'DELETE' });
      showFormMsg(`Customer ${account} deleted successfully.`, 'ok');
      if (activeAccount === account) resetForm();
      await loadCustomers();
    } catch (err) {
      showFormMsg(err.message || `Could not delete ${account}.`, 'err');
    }
  }

  // ---------- form: validation ----------

  function clearFieldErrors() {
    Object.keys(VALIDATORS).forEach((field) => {
      el('err_' + field).textContent = '';
      el('f_' + field).classList.remove('invalid');
    });
  }

  function validateForm(data, fieldsToCheck) {
    let valid = true;
    fieldsToCheck.forEach((field) => {
      const validator = VALIDATORS[field];
      const value = data[field] !== undefined ? String(data[field]) : '';
      const input = el('f_' + field);
      const errNode = el('err_' + field);
      if (!validator.test(value)) {
        valid = false;
        input.classList.add('invalid');
        errNode.textContent = validator.message;
      } else {
        input.classList.remove('invalid');
        errNode.textContent = '';
      }
    });
    return valid;
  }

  // ---------- form: mode switching ----------

  function setFieldsDisabled(fields, disabled) {
    fields.forEach((f) => { el('f_' + f).disabled = disabled; });
  }

  function enterCreateMode() {
    formMode = 'create';
    activeAccount = null;
    customerForm.reset();
    clearFieldErrors();
    clearFormMsg();

    setFieldsDisabled(CUSTOMER_FIELDS, false);
    setFieldsDisabled(LOAN_FIELDS, false);
    loanFieldset.classList.remove('disabled');
    loanSectionHeading.textContent = 'Loan Details (required to create a customer)';

    modeLabel.textContent = 'New Customer';
    cancelModeBtn.style.display = 'none';
    submitBtn.textContent = 'Save Customer';
  }

  function enterEditMode(account) {
    const c = findCustomer(account);
    if (!c) {
      showFormMsg(`No customer found for account ${account}.`, 'err');
      return;
    }

    formMode = 'edit';
    activeAccount = account;
    clearFieldErrors();
    clearFormMsg();

    el('f_account_number').value = c.account_number;
    el('f_customer_name').value = c.customer_name;
    el('f_customer_dob').value = c.customer_dob;
    el('f_customer_phone_number').value = c.customer_phone_number;
    el('f_customer_address').value = c.customer_address;
    LOAN_FIELDS.forEach((f) => { el('f_' + f).value = ''; });

    setFieldsDisabled(CUSTOMER_FIELDS, false);
    el('f_account_number').disabled = true; // account number never editable
    setFieldsDisabled(LOAN_FIELDS, true);
    loanFieldset.classList.add('disabled');
    loanSectionHeading.textContent = 'Loan details are not editable here';

    modeLabel.textContent = `Editing ${account}`;
    cancelModeBtn.style.display = 'inline-block';
    submitBtn.textContent = `Update ${account}`;

    scrollFormIntoView();
  }

  function enterApplyLoanMode(account) {
    const c = findCustomer(account);
    if (!c) {
      showFormMsg(`No customer found for account ${account}.`, 'err');
      return;
    }

    formMode = 'apply';
    activeAccount = account;
    clearFieldErrors();
    clearFormMsg();

    el('f_account_number').value = c.account_number;
    el('f_customer_name').value = c.customer_name;
    el('f_customer_dob').value = c.customer_dob;
    el('f_customer_phone_number').value = c.customer_phone_number;
    el('f_customer_address').value = c.customer_address;
    LOAN_FIELDS.forEach((f) => { el('f_' + f).value = ''; });

    setFieldsDisabled(CUSTOMER_FIELDS, true);
    setFieldsDisabled(LOAN_FIELDS, false);
    loanFieldset.classList.remove('disabled');
    loanSectionHeading.textContent = 'New Loan Details';

    const existingTypes = (c.loans || []).map((l) => l.loan_type);
    if (existingTypes.length) {
      showFormMsg(`Existing loans: ${existingTypes.map((t) => LOAN_LABELS[t] || t).join(', ')}. Pick a different type below.`, 'info');
    }

    modeLabel.textContent = `Apply New Loan — ${account}`;
    cancelModeBtn.style.display = 'inline-block';
    submitBtn.textContent = 'Apply Loan';

    scrollFormIntoView();
  }

  function scrollFormIntoView() {
    document.getElementById('input-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function resetForm() {
    enterCreateMode();
  }

  cancelModeBtn.addEventListener('click', resetForm);
  resetBtn.addEventListener('click', resetForm);

  // ---------- form: submit ----------

  customerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFieldErrors();
    clearFormMsg();

    const data = {
      account_number: el('f_account_number').value.trim(),
      customer_name: el('f_customer_name').value.trim(),
      customer_dob: el('f_customer_dob').value,
      customer_address: el('f_customer_address').value.trim(),
      customer_phone_number: el('f_customer_phone_number').value.trim(),
      loan_type: el('f_loan_type').value,
      loan_amount: el('f_loan_amount').value,
      loan_tenure: el('f_loan_tenure').value,
    };

    if (formMode === 'create') {
      if (!validateForm(data, [...CUSTOMER_FIELDS, ...LOAN_FIELDS])) return;

      if (findCustomer(data.account_number)) {
        el('f_account_number').classList.add('invalid');
        el('err_account_number').textContent = 'Account number already exists.';
        showFormMsg('Account number already exists.', 'err');
        return;
      }

      submitBtn.disabled = true;
      try {
        await api('', {
          method: 'POST',
          body: JSON.stringify({
            ...data,
            loan_amount: Number(data.loan_amount),
            loan_tenure: Number(data.loan_tenure),
          }),
        });
        showFormMsg(`Customer ${data.account_number} created successfully.`, 'ok');
        resetForm();
        await loadCustomers();
      } catch (err) {
        showFormMsg(err.message || 'Could not create customer.', 'err');
      } finally {
        submitBtn.disabled = false;
      }
      return;
    }

    if (formMode === 'edit') {
      if (!validateForm(data, CUSTOMER_FIELDS.filter((f) => f !== 'account_number'))) return;

      submitBtn.disabled = true;
      try {
        await api(`/${encodeURIComponent(activeAccount)}`, {
          method: 'PUT',
          body: JSON.stringify({
            customer_name: data.customer_name,
            customer_dob: data.customer_dob,
            customer_address: data.customer_address,
            customer_phone_number: data.customer_phone_number,
          }),
        });
        showFormMsg(`Customer ${activeAccount} updated successfully.`, 'ok');
        resetForm();
        await loadCustomers();
      } catch (err) {
        showFormMsg(err.message || 'Could not update customer.', 'err');
      } finally {
        submitBtn.disabled = false;
      }
      return;
    }

    if (formMode === 'apply') {
      if (!validateForm(data, LOAN_FIELDS)) return;

      const c = findCustomer(activeAccount);
      const alreadyHas = c && (c.loans || []).some((l) => l.loan_type === data.loan_type);
      if (alreadyHas) {
        el('f_loan_type').classList.add('invalid');
        const label = LOAN_LABELS[data.loan_type] || data.loan_type;
        el('err_loan_type').textContent = `Customer already has an active ${label}.`;
        showFormMsg(`This customer already has an active ${label}.`, 'err');
        return;
      }

      submitBtn.disabled = true;
      try {
        await api(`/${encodeURIComponent(activeAccount)}/loans`, {
          method: 'POST',
          body: JSON.stringify({
            loan_type: data.loan_type,
            loan_amount: Number(data.loan_amount),
            loan_tenure: Number(data.loan_tenure),
          }),
        });
        showFormMsg('Loan applied successfully.', 'ok');
        resetForm();
        await loadCustomers();
      } catch (err) {
        showFormMsg(err.message || 'Could not apply loan.', 'err');
      } finally {
        submitBtn.disabled = false;
      }
    }
  });

  ['f_account_number', 'f_customer_phone_number'].forEach((id) => {
    el(id).addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g, '');
    });
  });

  // ---------- search / refresh ----------

  searchInput.addEventListener('input', renderTable);
  refreshBtn.addEventListener('click', loadCustomers);

  // ---------- init ----------

  enterCreateMode();
  loadCustomers();
})();
