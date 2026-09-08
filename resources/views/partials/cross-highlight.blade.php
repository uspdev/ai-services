@push('styles')
  <style>
    .resposta-editavel {
      min-height: 280px;
      overflow: hidden;
      background-color: #fff;
      line-height: 1.2;
      cursor: text;
    }

    .resposta-editavel .referencia {
      margin-bottom: 0.5rem;
      padding: 0.15rem 0.25rem;
      border-radius: 0.25rem;
      transition: background-color 0.15s ease;
    }

    .resposta-editavel .referencia:last-child {
      margin-bottom: 0;
    }

    .explicacao {
      min-height: 280px;
      overflow: hidden;
      background-color: #e9ecef !important;
      line-height: 1.2;
    }

    .explicacao-item {
      margin-bottom: 0.5rem;
      padding: 0.15rem 0.25rem;
      border-radius: 0.25rem;
      transition: background-color 0.15s ease;
    }

    .explicacao-item:last-child {
      margin-bottom: 0;
    }

    .referencia.destacada,
    .explicacao-item.destacada {
      background-color: #fff3cd;
    }
  </style>
@endpush

<script>
  window.UICrossHighlight = (() => {
    const destacar = (containerResposta, containerExplicacao, index, ativo) => {
      containerResposta
        ?.querySelector(`.referencia[data-index="${index}"]`)
        ?.classList.toggle('destacada', ativo);

      containerExplicacao
        ?.querySelector(`.explicacao-item[data-index="${index}"]`)
        ?.classList.toggle('destacada', ativo);
    };

    const iniciar = (containerResposta, containerExplicacao) => {
      const vincularEventos = (elementos) => {
        elementos.forEach(item => {
          const index = item.dataset.index;
          item.addEventListener('mouseenter', () =>
            destacar(containerResposta, containerExplicacao, index, true)
          );
          item.addEventListener('mouseleave', () =>
            destacar(containerResposta, containerExplicacao, index, false)
          );
        });
      };

      if (containerExplicacao) {
        vincularEventos(containerExplicacao.querySelectorAll('.explicacao-item'));
      }
      if (containerResposta) {
        vincularEventos(containerResposta.querySelectorAll('.referencia'));
      }
    };

    return { iniciar };
  })();
</script>
