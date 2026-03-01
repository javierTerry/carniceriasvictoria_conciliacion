<?php
$title = "Dashboard - ";
include "head.php";
include "sidebar.php";

$id = $_SESSION['user_kind'] ?? 0; // Using user_kind as per previous context
$hoy = date('Y-m-d');

// Static Data Fetching (Current Totals)
$sql_resumen = "SELECT count(mov_id) as ventas, sum(sumqty) as kilos, sum(sumimp) as monto 
                FROM vtahead WHERE is_active=1 and created_at=?";
$stmt = mysqli_prepare($conexion, $sql_resumen);
mysqli_stmt_bind_param($stmt, "s", $hoy);
mysqli_stmt_execute($stmt);
$r = mysqli_fetch_array(mysqli_stmt_get_result($stmt));
$ventas = $r['ventas'] ?? 0;
$kilos = $r['kilos'] ?? 0;
$monto = $r['monto'] ?? 0;

// Balance info
$sql_caja = "SELECT caja FROM emprsa LIMIT 1";
$res_caja = mysqli_query($conexion, $sql_caja);
$r_caja = mysqli_fetch_array($res_caja);
$caja = $r_caja['caja'] ?? 0;

?>

<style>
     .chart-container {
          position: relative;
          height: 300px;
          width: 100%;
          margin-bottom: 20px;
     }

     .x_panel {
          border-radius: 8px;
          box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
          border: none;
     }

     .tile-stats {
          border-radius: 8px;
          transition: transform 0.2s;
     }

     .tile-stats:hover {
          transform: translateY(-5px);
     }
</style>

<div class="right_col" role="main">
     <div class="">
          <div class="page-title">
               <div class="row">
                    <div class="col-md-12">
                         <h3>Panel de Control <small>Resumen interactivo de operaciones</small></h3>
                    </div>
               </div>

               <!-- Metric Cards -->
               <div class="row top_tiles">
                    <div class="animated flipInY col-lg-3 col-md-3 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-shopping-cart" style="color: #26B99A;"></i></div>
                              <div class="count"><?php echo number_format($ventas, 0); ?></div>
                              <h3>Ventas Hoy</h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-3 col-md-3 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-balance-scale" style="color: #34495E;"></i></div>
                              <div class="count"><?php echo number_format($kilos, 2) ?></div>
                              <h3>Kilos Hoy</h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-3 col-md-3 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-money" style="color: #3498DB;"></i></div>
                              <div class="count">$<?php echo number_format($monto, 2) ?></div>
                              <h3>Monto Hoy</h3>
                         </div>
                    </div>
                    <div class="animated flipInY col-lg-3 col-md-3 col-sm-6 col-xs-12">
                         <div class="tile-stats">
                              <div class="icon"><i class="fa fa-university" style="color: #F39C12;"></i></div>
                              <div class="count">$<?php echo number_format($caja, 2); ?></div>
                              <h3>Caja</h3>
                         </div>
                    </div>
               </div>

               <div class="clearfix"></div>

               <!-- Charts Section -->
               <div class="row">
                    <!-- Trend Chart -->
                    <div class="col-md-8 col-sm-12 col-xs-12">
                         <div class="x_panel">
                              <div class="x_title">
                                   <h2>Tendencia de Ventas <small>Últimos 30 días</small></h2>
                                   <div class="clearfix"></div>
                              </div>
                              <div class="x_content">
                                   <div class="chart-container">
                                        <canvas id="trendChart"></canvas>
                                   </div>
                              </div>
                         </div>
                    </div>

                    <!-- Top Customers -->
                    <div class="col-md-4 col-sm-12 col-xs-12">
                         <div class="x_panel">
                              <div class="x_title">
                                   <h2>Mejores Clientes <small>Total Mensual</small></h2>
                                   <div class="clearfix"></div>
                              </div>
                              <div class="x_content">
                                   <div class="chart-container">
                                        <canvas id="topCustomersChart"></canvas>
                                   </div>
                              </div>
                         </div>
                    </div>
               </div>

               <div class="row">
                    <!-- Hourly Distribution -->
                    <div class="col-md-12 col-sm-12 col-xs-12">
                         <div class="x_panel">
                              <div class="x_title">
                                   <h2>Distribución Horaria <small>Ventas de Hoy</small></h2>
                                   <div class="clearfix"></div>
                              </div>
                              <div class="x_content">
                                   <div class="chart-container" style="height: 250px;">
                                        <canvas id="hourlyChart"></canvas>
                                   </div>
                              </div>
                         </div>
                    </div>
               </div>

          </div>
     </div>
</div>

<!-- Load local Chart.js v2.1.4 before footer to avoid conflicts with custom.min.js -->
<script src="js/Chart.js/dist/Chart.js"></script>

<?php include "footer.php" ?>

<!-- Scripts for Charts -->
<script>
$(document).ready(function () {
    $.ajax({
        url: 'ajax/dashboard_data.php',
        type: 'GET',
        dataType: 'json',
        success: function (data) {
            renderTrends(data.trends);
            renderHourly(data.hourly);
            renderTopCustomers(data.top_customers);
        },
        error: function () {
            console.error("Error al cargar datos del dashboard.");
        }
    });

    function renderTrends(trends) {
        var ctx = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trends.map(function(t) { return t.date; }),
                datasets: [{
                    label: 'Ventas ($)',
                    data: trends.map(function(t) { return t.total; }),
                    borderColor: '#26B99A',
                    backgroundColor: 'rgba(38, 185, 154, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    lineTension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: true }
                    }]
                }
            }
        });
    }

    function renderHourly(hourly) {
        var ctx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: hourly.map(function(h) { return h.hour; }),
                datasets: [{
                    label: 'Ventas x Hora ($)',
                    data: hourly.map(function(h) { return h.total; }),
                    backgroundColor: '#34495E'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: true }
                    }]
                }
            }
        });
    }

    function renderTopCustomers(top) {
        var ctx = document.getElementById('topCustomersChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: top.map(function(c) { return c.name; }),
                datasets: [{
                    data: top.map(function(c) { return c.total; }),
                    backgroundColor: ['#26B99A', '#3498DB', '#F39C12', '#E74C3C', '#9B59B6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom'
                }
            }
        });
    }
});
</script>