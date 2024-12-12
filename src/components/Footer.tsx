import React from 'react';
import { Link } from 'react-router-dom';
import { Facebook, Instagram, Twitter, Mail } from 'lucide-react';

export function Footer() {
  return (
    <footer className="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
      <div className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          <div className="space-y-4">
            <h3 className="text-lg font-bold text-[#990047]">PartagonsLaForet</h3>
            <p className="text-gray-600 dark:text-gray-300">
              Découvrez et partagez des événements sportifs près de chez vous
            </p>
            <div className="flex space-x-4">
              <a href="#" className="text-gray-400 hover:text-[#990047]">
                <Facebook size={20} />
              </a>
              <a href="#" className="text-gray-400 hover:text-[#990047]">
                <Instagram size={20} />
              </a>
              <a href="#" className="text-gray-400 hover:text-[#990047]">
                <Twitter size={20} />
              </a>
              <a href="#" className="text-gray-400 hover:text-[#990047]">
                <Mail size={20} />
              </a>
            </div>
          </div>

          <div>
            <h3 className="text-lg font-semibold mb-4 dark:text-white">Navigation</h3>
            <ul className="space-y-2">
              <li>
                <Link to="/" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Accueil
                </Link>
              </li>
              <li>
                <Link to="/events" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Événements
                </Link>
              </li>
              <li>
                <Link to="/create" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Créer un événement
                </Link>
              </li>
            </ul>
          </div>

          <div>
            <h3 className="text-lg font-semibold mb-4 dark:text-white">Catégories</h3>
            <ul className="space-y-2">
              <li>
                <Link to="/?category=running" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Course à pied
                </Link>
              </li>
              <li>
                <Link to="/?category=hiking" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Randonnée
                </Link>
              </li>
              <li>
                <Link to="/?category=cycling" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Cyclisme
                </Link>
              </li>
            </ul>
          </div>

          <div>
            <h3 className="text-lg font-semibold mb-4 dark:text-white">Contact</h3>
            <ul className="space-y-2">
              <li>
                <Link to="/about" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  À propos
                </Link>
              </li>
              <li>
                <Link to="/contact" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Nous contacter
                </Link>
              </li>
              <li>
                <Link to="/privacy" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Politique de confidentialité
                </Link>
              </li>
              <li>
                <Link to="/terms" className="text-gray-600 dark:text-gray-300 hover:text-[#990047]">
                  Conditions d'utilisation
                </Link>
              </li>
            </ul>
          </div>
        </div>

        <div className="border-t border-gray-200 dark:border-gray-700 mt-8 pt-8 text-center">
          <p className="text-gray-600 dark:text-gray-300">
            © {new Date().getFullYear()} PartagonsLaForet. Tous droits réservés.
          </p>
        </div>
      </div>
    </footer>
  );
}