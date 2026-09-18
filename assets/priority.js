(function () {
  var matrix = window.TD_PRIORITY_MATRIX || {};
  var colors = {
    Critical: { bg: '#FEE2E2', color: '#dc2626', border: '#fca5a5', dot: '🔴' },
    High: { bg: '#FFEDD5', color: '#ea580c', border: '#fdba74', dot: '🟠' },
    Medium: { bg: '#FEF9C3', color: '#ca8a04', border: '#fde047', dot: '🟡' },
    Low: { bg: '#DCFCE7', color: '#16a34a', border: '#86efac', dot: '🟢' },
  };
  var descriptions = {
    Critical: 'Immediate operational, financial, security, access-control, or core-system impact.',
    High: 'Significant disruption to an important business function, but operations can continue temporarily.',
    Medium: 'Individual or team productivity impact with a reasonable workaround available.',
    Low: 'Minor inconvenience involving a non-essential service, device, or function.',
  };

  document.querySelectorAll('[data-priority-form]').forEach(function (form) {
    var dept = form.querySelector('[data-priority-dept]');
    var cat = form.querySelector('[data-priority-cat]');
    var indicator = form.querySelector('[data-priority-indicator]');
    var hidden = form.querySelector('[data-priority-value]');
    var description = form.querySelector('[data-priority-description]');
    if (!dept || !cat || !indicator) return;

    function updatePriority() {
      var priority = matrix[dept.value] && matrix[dept.value][cat.value];
      var scheme = colors[priority];
      if (!scheme) {
        indicator.textContent = 'Select department and category to determine priority.';
        indicator.style.background = '#f4f3f1';
        indicator.style.color = '#5C5854';
        indicator.style.borderColor = '#e4e3e0';
        if (hidden) hidden.value = '';
        if (description) description.textContent = '';
        return;
      }
      indicator.textContent = scheme.dot + '  ' + priority;
      indicator.style.background = scheme.bg;
      indicator.style.color = scheme.color;
      indicator.style.borderColor = scheme.border;
      if (hidden) hidden.value = priority;
      if (description) description.textContent = descriptions[priority];
    }

    dept.addEventListener('change', updatePriority);
    cat.addEventListener('change', updatePriority);
    updatePriority();
  });
})();
