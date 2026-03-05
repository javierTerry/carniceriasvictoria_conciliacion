$(document).ready(function () {
    $.ajax({
        url: "ajax/dashboard_data.php",
        type: "GET",
        dataType: "json",
        success: function (data) {
            renderTrends(data.trends);
            renderHourly(data.hourly);
            renderTopCustomers(data.top_customers);
        },
        error: function () {
            console.error("Error al cargar datos del dashboard.");
        },
    });

    function renderTrends(trends) {
        var ctx = document.getElementById("trendChart").getContext("2d");
        new Chart(ctx, {
            type: "line",
            data: {
                labels: trends.map(function (t) {
                    return t.date;
                }),
                datasets: [
                    {
                        label: "Ventas ($)",
                        data: trends.map(function (t) {
                            return t.total;
                        }),
                        borderColor: "#26B99A",
                        backgroundColor: "rgba(38, 185, 154, 0.1)",
                        borderWidth: 2,
                        fill: true,
                        lineTension: 0.4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [
                        {
                            ticks: { beginAtZero: true },
                        },
                    ],
                },
            },
        });
    }

    function renderHourly(hourly) {
        var ctx = document.getElementById("hourlyChart").getContext("2d");
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: hourly.map(function (h) {
                    return h.hour;
                }),
                datasets: [
                    {
                        label: "Ventas x Hora ($)",
                        data: hourly.map(function (h) {
                            return h.total;
                        }),
                        backgroundColor: "#34495E",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [
                        {
                            ticks: { beginAtZero: true },
                        },
                    ],
                },
            },
        });
    }

    function renderTopCustomers(top) {
        var ctx = document.getElementById("topCustomersChart").getContext("2d");
        new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: top.map(function (c) {
                    return c.name;
                }),
                datasets: [
                    {
                        data: top.map(function (c) {
                            return c.total;
                        }),
                        backgroundColor: [
                            "#26B99A",
                            "#3498DB",
                            "#F39C12",
                            "#E74C3C",
                            "#9B59B6",
                        ],
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: "bottom",
                },
            },
        });
    }
});
