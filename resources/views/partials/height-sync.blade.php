<script>
  window.UIHeightSync = (() => {
    const ajustar = (...elementos) => {
      const elementosValidos = elementos.filter(Boolean);
      if (!elementosValidos.length) return;

      elementosValidos.forEach(el => el.style.height = 'auto');

      requestAnimationFrame(() => {
        const alturas = elementosValidos.map(el => el.scrollHeight);
        const maiorAltura = Math.max(...alturas, 280);

        elementosValidos.forEach(el => el.style.height = `${maiorAltura}px`);
      });
    };

    const iniciar = (elementos) => {
      const atualizar = () => ajustar(...elementos);

      let timer;
      window.addEventListener('resize', () => {
        clearTimeout(timer);
        timer = setTimeout(atualizar, 100);
      });

      window.addEventListener('diff:updated', atualizar);
      atualizar();
    };

    return { iniciar, ajustar };
  })();
</script>
