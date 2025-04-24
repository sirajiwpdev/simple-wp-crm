document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('swcrm_leads_chart').getContext('2d');

    const chart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: swcrm_data.labels,
            datasets: [{
                label: 'Leads by Status',
                data: swcrm_data.counts,
                backgroundColor: [
                    '#0073aa',
                    '#46b450',
                    '#f56b6b',
                    '#f0ad4e'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
});
