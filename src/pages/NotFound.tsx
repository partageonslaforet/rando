export function NotFound() {
  console.log('❌ NotFound rendering');
  
  return (
    <div className="min-h-screen bg-white dark:bg-gray-900 flex items-center justify-center">
      <div className="text-center">
        <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">
          404 - Page non trouvée
        </h1>
        <p className="text-gray-600 dark:text-gray-300">
          La page que vous recherchez n'existe pas.
        </p>
      </div>
    </div>
  );
}
