/* Página pública de agendamento — máscara de telefone e busca de horários livres */

(function () {
  'use strict';

  var base = window.BASE_URL || '';
  var servico = document.getElementById('servico');
  var data = document.getElementById('data');
  var hora = document.getElementById('hora');
  var dica = document.getElementById('dicaHorarios');
  var whats = document.getElementById('whatsapp');

  /* ---- máscara (11) 90000-0000 ---- */
  if (whats) {
    whats.addEventListener('input', function () {
      var n = whats.value.replace(/\D/g, '').slice(0, 11);
      var saida = n;
      if (n.length > 2) { saida = '(' + n.slice(0, 2) + ') ' + n.slice(2); }
      if (n.length > 6) {
        var corte = n.length > 10 ? 7 : 6;
        saida = '(' + n.slice(0, 2) + ') ' + n.slice(2, corte) + '-' + n.slice(corte);
      }
      whats.value = saida;
    });
  }

  if (!servico || !data || !hora) { return; }

  var escolhido = hora.dataset.escolhido || '';

  function limpar(texto, habilitado) {
    hora.innerHTML = '<option value="">' + texto + '</option>';
    hora.disabled = !habilitado;
  }

  function buscar() {
    if (!servico.value || !data.value) {
      limpar('Escolha o serviço e a data', false);
      dica.textContent = '';
      return;
    }

    limpar('Procurando horários…', false);
    dica.textContent = '';

    var url = base + '/publico/horarios.php?data=' + encodeURIComponent(data.value) +
              '&servico=' + encodeURIComponent(servico.value);

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (dados) {
        if (!dados.horarios || !dados.horarios.length) {
          limpar('Sem horário livre nesse dia', false);
          dica.textContent = dados.aviso || 'Tente outra data.';
          dica.className = 'dica dica--ruim';
          return;
        }

        hora.innerHTML = '<option value="">Escolha um horário</option>';
        dados.horarios.forEach(function (h) {
          var op = document.createElement('option');
          op.value = h;
          op.textContent = h;
          if (h === escolhido) { op.selected = true; }
          hora.appendChild(op);
        });
        hora.disabled = false;

        dica.textContent = dados.horarios.length + ' horário(s) livre(s) nesse dia.';
        dica.className = 'dica dica--ok';
      })
      .catch(function () {
        limpar('Não foi possível carregar', false);
        dica.textContent = 'Erro de conexão. Tente de novo.';
        dica.className = 'dica dica--ruim';
      });
  }

  servico.addEventListener('change', buscar);
  data.addEventListener('change', buscar);

  // se a página voltou com erro, recarrega a lista mantendo o que foi escolhido
  if (servico.value && data.value) { buscar(); }
})();
