(function ($) {
  'use strict';
  
  let barChartInstance = null;
  let doughnutChartInstance = null;
  
  if ($("#visit-sale-chart").length) {
    const ctx = document.getElementById('visit-sale-chart');

    var graphGradient1 = document.getElementById('visit-sale-chart').getContext("2d");
    var graphGradient2 = document.getElementById('visit-sale-chart').getContext("2d");
    var graphGradient3 = document.getElementById('visit-sale-chart').getContext("2d");

    var gradientStrokeViolet = graphGradient1.createLinearGradient(0, 0, 0, 181);
    gradientStrokeViolet.addColorStop(0, 'rgba(218, 140, 255, 1)');
    gradientStrokeViolet.addColorStop(1, 'rgba(154, 85, 255, 1)');
    var gradientLegendViolet = 'linear-gradient(to right, rgba(218, 140, 255, 1), rgba(154, 85, 255, 1))';

    var gradientStrokeBlue = graphGradient2.createLinearGradient(0, 0, 0, 360);
    gradientStrokeBlue.addColorStop(0, 'rgba(54, 215, 232, 1)');
    gradientStrokeBlue.addColorStop(1, 'rgba(177, 148, 250, 1)');
    var gradientLegendBlue = 'linear-gradient(to right, rgba(54, 215, 232, 1), rgba(177, 148, 250, 1))';

    var gradientStrokeRed = graphGradient3.createLinearGradient(0, 0, 0, 300);
    gradientStrokeRed.addColorStop(0, 'rgba(255, 191, 150, 1)');
    gradientStrokeRed.addColorStop(1, 'rgba(254, 112, 150, 1)');
    var gradientLegendRed = 'linear-gradient(to right, rgba(255, 191, 150, 1), rgba(254, 112, 150, 1))';
    const bgColor1 = ["rgba(218, 140, 255, 1)"];
    const bgColor2 = ["rgba(54, 215, 232, 1"];
    const bgColor3 = ["rgba(255, 191, 150, 1)"];

    barChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG'],
        datasets: [{
          label: "Patients",
          borderColor: gradientStrokeViolet,
          backgroundColor: gradientStrokeViolet,
          fillColor: bgColor1,
          hoverBackgroundColor: gradientStrokeViolet,
          pointRadius: 0,
          fill: false,
          borderWidth: 1,
          fill: 'origin',
          data: [0, 0, 0, 0, 0, 0, 0, 0],
          barPercentage: 0.5,
          categoryPercentage: 0.5,
        },
        {
          label: "Clinics",
          borderColor: gradientStrokeRed,
          backgroundColor: gradientStrokeRed,
          hoverBackgroundColor: gradientStrokeRed,
          fillColor: bgColor2,
          pointRadius: 0,
          fill: false,
          borderWidth: 1,
          fill: 'origin',
          data: [0, 0, 0, 0, 0, 0, 0, 0],
          barPercentage: 0.5,
          categoryPercentage: 0.5,
        },
        {
          label: "Appointments",
          borderColor: gradientStrokeBlue,
          backgroundColor: gradientStrokeBlue,
          hoverBackgroundColor: gradientStrokeBlue,
          fillColor: bgColor3,
          pointRadius: 0,
          fill: false,
          borderWidth: 1,
          fill: 'origin',
          data: [0, 0, 0, 0, 0, 0, 0, 0],
          barPercentage: 0.5,
          categoryPercentage: 0.5,
        }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        elements: {
          line: {
            tension: 0.4,
          },
        },
        scales: {
          y: {
            display: false,
            grid: {
              display: true,
              drawOnChartArea: true,
              drawTicks: false,
            },
          },
          x: {
            display: true,
            grid: {
              display: false,
            },
          }
        },
        plugins: {
          legend: {
            display: false,
          }
        }
      },
      plugins: [{
        afterDatasetUpdate: function (chart, args, options) {
          const chartId = chart.canvas.id;
          var i;
          const legendId = `${chartId}-legend`;
          const ul = document.createElement('ul');
          for (i = 0; i < chart.data.datasets.length; i++) {
            ul.innerHTML += `
              <li>
                <span style="background-color: ${chart.data.datasets[i].fillColor}"></span>
                ${chart.data.datasets[i].label}
              </li>
            `;
          }
          // alert(chart.data.datasets[0].backgroundColor);
          return document.getElementById(legendId).appendChild(ul);
        }
      }]
    });
  }

  if ($("#traffic-chart").length) {
    const ctx = document.getElementById('traffic-chart');

    var graphGradient1 = document.getElementById("traffic-chart").getContext('2d');
    var graphGradient2 = document.getElementById("traffic-chart").getContext('2d');
    var graphGradient3 = document.getElementById("traffic-chart").getContext('2d');

    var gradientStrokeBlue = graphGradient1.createLinearGradient(0, 0, 0, 181);
    gradientStrokeBlue.addColorStop(0, 'rgba(54, 215, 232, 1)');
    gradientStrokeBlue.addColorStop(1, 'rgba(177, 148, 250, 1)');
    var gradientLegendBlue = 'rgba(54, 215, 232, 1)';

    var gradientStrokeRed = graphGradient2.createLinearGradient(0, 0, 0, 50);
    gradientStrokeRed.addColorStop(0, 'rgba(255, 191, 150, 1)');
    gradientStrokeRed.addColorStop(1, 'rgba(254, 112, 150, 1)');
    var gradientLegendRed = 'rgba(254, 112, 150, 1)';

    var gradientStrokeGreen = graphGradient3.createLinearGradient(0, 0, 0, 300);
    gradientStrokeGreen.addColorStop(0, 'rgba(6, 185, 157, 1)');
    gradientStrokeGreen.addColorStop(1, 'rgba(132, 217, 210, 1)');
    var gradientLegendGreen = 'rgba(6, 185, 157, 1)';

    // const bgColor1 = ["rgba(54, 215, 232, 1)"];
    // const bgColor2 = ["rgba(255, 191, 150, 1"];
    // const bgColor3 = ["rgba(6, 185, 157, 1)"];

    doughnutChartInstance = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Pending 0%', 'Completed 0%', 'Cancelled 0%'],
        datasets: [{
          data: [0, 0, 1],
          backgroundColor: [gradientStrokeBlue, gradientStrokeGreen, gradientStrokeRed],
          hoverBackgroundColor: [
            gradientStrokeBlue,
            gradientStrokeGreen,
            gradientStrokeRed
          ],
          borderColor: [
            gradientStrokeBlue,
            gradientStrokeGreen,
            gradientStrokeRed
          ],
          legendColor: [
            gradientLegendBlue,
            gradientLegendGreen,
            gradientLegendRed
          ]
        }]
      },
      options: {
        cutout: 50,
        animationEasing: "easeOutBounce",
        animateRotate: true,
        animateScale: false,
        responsive: true,
        maintainAspectRatio: true,
        showScale: true,
        legend: false,
        plugins: {
          legend: {
            display: false,
          }
        }
      },
      plugins: [{
        afterDatasetUpdate: function (chart, args, options) {
          const chartId = chart.canvas.id;
          var i;
          const legendId = `${chartId}-legend`;
          const ul = document.createElement('ul');
          for (i = 0; i < chart.data.datasets[0].data.length; i++) {
            ul.innerHTML += `
                <li>
                  <span style="background-color: ${chart.data.datasets[0].legendColor[i]}"></span>
                  ${chart.data.labels[i]}
                </li>
              `;
          }
          return document.getElementById(legendId).appendChild(ul);
        }
      }]
    });
  }



  if ($("#inline-datepicker").length) {
    $('#inline-datepicker').datepicker({
      enableOnReadonly: true,
      todayHighlight: true,
    });
  }
  const proBannerEl = document.querySelector('#proBanner');
  const navbarEl = document.querySelector('.navbar');
  const pageBodyWrapperEl = document.querySelector('.page-body-wrapper');

  if (!proBannerEl) {
    if (navbarEl) {
      navbarEl.classList.add('fixed-top');
      navbarEl.classList.remove('pt-5');
      navbarEl.classList.remove('mt-3');
    }
    if (pageBodyWrapperEl) {
      pageBodyWrapperEl.classList.remove('proBanner-padding-top');
      pageBodyWrapperEl.classList.remove('pt-0');
    }
    return;
  }

  if ($.cookie('purple-pro-banner') != "true") {
    if (proBannerEl) proBannerEl.classList.add('d-flex');
    if (navbarEl) navbarEl.classList.remove('fixed-top');
  } else {
    if (proBannerEl) proBannerEl.classList.add('d-none');
    if (navbarEl) navbarEl.classList.add('fixed-top');
  }

  if ($(".navbar").hasClass("fixed-top")) {
    if (pageBodyWrapperEl) pageBodyWrapperEl.classList.remove('pt-0');
    if (navbarEl) navbarEl.classList.remove('pt-5');
  } else {
    if (pageBodyWrapperEl) pageBodyWrapperEl.classList.add('pt-0');
    if (navbarEl) navbarEl.classList.add('pt-5');
    if (navbarEl) navbarEl.classList.add('mt-3');

  }
  const bannerCloseEl = document.querySelector('#bannerClose');
  if (bannerCloseEl) {
    bannerCloseEl.addEventListener('click', function () {
      if (proBannerEl) proBannerEl.classList.add('d-none');
      if (proBannerEl) proBannerEl.classList.remove('d-flex');
      if (navbarEl) navbarEl.classList.remove('pt-5');
      if (navbarEl) navbarEl.classList.add('fixed-top');
      if (pageBodyWrapperEl) pageBodyWrapperEl.classList.add('proBanner-padding-top');
      if (navbarEl) navbarEl.classList.remove('mt-3');
      var date = new Date();
      date.setTime(date.getTime() + 24 * 60 * 60 * 1000);
      $.cookie('purple-pro-banner', "true", {
        expires: date
      });
    });
  }

  // Listen for chart data updates from admin-dashboard.js
  document.addEventListener('updateCharts', function(e) {
    const { monthlyData, months, appointmentStats } = e.detail;

    // Update bar chart (Status chart)
    if (barChartInstance && monthlyData && months) {
      barChartInstance.data.labels = months;
      
      const patientsData = monthlyData.map(d => d.patients);
      const clinicsData = monthlyData.map(d => d.clinics);
      const appointmentsData = monthlyData.map(d => d.appointments);
      
      barChartInstance.data.datasets[0].data = patientsData;
      barChartInstance.data.datasets[1].data = clinicsData;
      barChartInstance.data.datasets[2].data = appointmentsData;
      
      barChartInstance.update();
    }

    // Update doughnut chart (Scheduling chart)
    if (doughnutChartInstance && appointmentStats) {
      const { pending, completed, cancelled, pending_percent, completed_percent, cancelled_percent } = appointmentStats;
      
      doughnutChartInstance.data.labels = [
        `Pending ${pending_percent}%`,
        `Completed ${completed_percent}%`,
        `Cancelled ${cancelled_percent}%`
      ];
      
      doughnutChartInstance.data.datasets[0].data = [pending || 1, completed || 1, cancelled || 1];
      doughnutChartInstance.update();
    }
  });

})(jQuery);