/* Painel — menu, máscara de telefone, horários de remarcação e mensagem ao cliente */

(function () {
  'use strict';

  /* ---- menu lateral no celular ---- */
  var botao = document.getElementById('abrirMenu');
  var menu = document.getElementById('menuLateral');

  if (botao && menu) {
    botao.addEventListener('click', function () {
      var aberto = menu.classList.toggle('aberta');
      botao.setAttribute('aria-expanded', String(aberto));
    });
  }

  /* ---- máscara de telefone em qualquer campo tel ---- */
  document.querySelectorAll('input[type="tel"]').forEach(function (campo) {
    campo.addEventListener('input', function () {
      var n = campo.value.replace(/\D/g, '').slice(0, 11);
      var saida = n;
      if (n.length > 2) { saida = '(' + n.slice(0, 2) + ') ' + n.slice(2); }
      if (n.length > 6) {
        var corte = n.length > 10 ? 7 : 6;
        saida = '(' + n.slice(0, 2) + ') ' + n.slice(2, corte) + '-' + n.slice(corte);
      }
      campo.value = saida;
    });
  });

  /* ---- ao trocar a data da sugestão, recarrega os horários livres ---- */
  var campoData = document.querySelector('[data-recarrega-horarios]');
  var campoHora = document.getElementById('sugestao_hora');

  if (campoData && campoHora) {
    campoData.addEventListener('change', function () {
      var url = '../publico/horarios.php?data=' + encodeURIComponent(campoData.value) +
                '&servico=' + encodeURIComponent(campoData.dataset.servico);

      campoHora.innerHTML = '<option value="">Procurando…</option>';
      campoHora.disabled = true;

      fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (dados) {
          if (!dados.horarios || !dados.horarios.length) {
            campoHora.innerHTML = '<option value="">Sem horário livre nesse dia</option>';
            return;
          }
          campoHora.innerHTML = '<option value="">Escolha um horário</option>';
          dados.horarios.forEach(function (h) {
            var op = document.createElement('option');
            op.value = h;
            op.textContent = h;
            campoHora.appendChild(op);
          });
          campoHora.disabled = false;
        })
        .catch(function () {
          campoHora.innerHTML = '<option value="">Erro ao carregar</option>';
        });
    });
  }

  /* ---- mensagem ao cliente: link sempre com o texto atual ---- */
  var mensagem = document.getElementById('mensagemCliente');
  var link = document.getElementById('linkZap');
  var copiar = document.getElementById('copiarMensagem');

  function atualizarLink() {
    if (!mensagem || !link) { return; }
    link.href = 'https://wa.me/' + link.dataset.numero +
                '?text=' + encodeURIComponent(mensagem.value);
  }

  if (mensagem && link) {
    mensagem.addEventListener('input', atualizarLink);
    atualizarLink();
  }

  if (copiar && mensagem) {
    copiar.addEventListener('click', function () {
      var antes = copiar.textContent;

      function avisar(texto) {
        copiar.textContent = texto;
        setTimeout(function () { copiar.textContent = antes; }, 1800);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(mensagem.value)
          .then(function () { avisar('Copiado!'); })
          .catch(function () { avisar('Não deu — copie à mão'); });
      } else {
        mensagem.select();
        avisar('Selecionado — use Ctrl+C');
      }
    });
  }
})();
