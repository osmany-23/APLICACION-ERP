// Loader liviano para transiciones ENTRE paginas ya dentro del layout
// (sidebar/header ya montados): a diferencia de <Loader /> (pantalla
// completa, usado antes de que exista sesion/layout), este solo ocupa el
// area de contenido, para que cambiar de pagina se sienta como una espera
// corta dentro del panel y no como una recarga completa de toda la app.
export default function PageLoader() {
  return (
    <div className="flex min-h-[60vh] w-full items-center justify-center">
      <div className="h-10 w-10 animate-spin rounded-full border-4 border-solid border-primary border-t-transparent"></div>
    </div>
  );
}
