(function () {
  function padEl() {
    return document.getElementById('rx-printable');
  }

  function fileName() {
    var el = padEl();
    return (el && el.dataset.filename) ? el.dataset.filename : 'MedCore-Prescription';
  }

  window.mcRxPrint = function () {
    window.print();
  };

  window.mcRxDownloadPdf = async function () {
    var el = padEl();
    var btn = document.getElementById('rx-download-btn');
    if (!el) return;

    if (typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') {
      window.print();
      return;
    }

    var prev = btn ? btn.innerHTML : '';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Preparing PDF…';
    }

    try {
      var canvas = await html2canvas(el, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#fffef9',
        logging: false,
        windowWidth: el.scrollWidth
      });

      var img = canvas.toDataURL('image/png');
      var pdf = new window.jspdf.jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4'
      });

      var pageW = pdf.internal.pageSize.getWidth();
      var pageH = pdf.internal.pageSize.getHeight();
      var imgW = pageW;
      var imgH = (canvas.height * imgW) / canvas.width;
      var y = 0;

      if (imgH <= pageH) {
        pdf.addImage(img, 'PNG', 0, 0, imgW, imgH);
      } else {
        var remaining = imgH;
        var position = 0;
        var pageCanvas = document.createElement('canvas');
        var ctx = pageCanvas.getContext('2d');
        var pageHeightPx = Math.floor((pageH * canvas.width) / pageW);

        while (remaining > 0) {
          pageCanvas.width = canvas.width;
          pageCanvas.height = Math.min(pageHeightPx, remaining);
          ctx.fillStyle = '#fffef9';
          ctx.fillRect(0, 0, pageCanvas.width, pageCanvas.height);
          ctx.drawImage(
            canvas,
            0, position, canvas.width, pageCanvas.height,
            0, 0, canvas.width, pageCanvas.height
          );
          var slice = pageCanvas.toDataURL('image/png');
          var sliceH = (pageCanvas.height * imgW) / canvas.width;
          if (position > 0) pdf.addPage();
          pdf.addImage(slice, 'PNG', 0, 0, imgW, sliceH);
          remaining -= pageHeightPx;
          position += pageHeightPx;
        }
      }

      pdf.save(fileName() + '.pdf');
    } catch (err) {
      console.error(err);
      window.print();
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = prev || '<i class="bi bi-download"></i> Download PDF';
      }
    }
  };
})();
