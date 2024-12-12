import mapImage from '../assets/images/map-hero.jpg';

export function CreateEventHero() {
  return (
    <div className="relative w-screen h-[400px] -mt-16 -mx-[calc((100vw-100%)/2)] bg-cover bg-center bg-no-repeat rounded-lg overflow-hidden"
         style={{ backgroundImage: `url(${mapImage})` }}>
      {/* Overlay sombre */}
      <div className="absolute inset-0 bg-black/40"></div>
      
      <div className="relative h-full flex items-center">
        <div className="mx-auto max-w-7xl px-6 w-full">
          <div className="mx-auto max-w-3xl text-center">
            <h2 className="text-4xl font-bold tracking-tight text-white sm:text-5xl mb-4">
              Créez votre événement
            </h2>
            <p className="text-xl text-gray-200 mb-8">
              Partagez votre passion pour la randonnée et rencontrez d'autres passionnés
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
