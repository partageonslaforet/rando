import React, { useState } from 'react';
import { MapContainer, TileLayer, Marker, useMapEvents } from 'react-leaflet';
import { Upload, Plus, Minus, MapPin } from 'lucide-react';
import type { Route, Contact } from '../types/event';
import { Header } from './Header';
import { Footer } from './Footer';
import { CreateEventHero } from './CreateEventHero';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix Leaflet default icon
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

interface LocationMarker {
  position: [number, number];
  setPosition: (position: [number, number]) => void;
}

function LocationMarkerComponent({ position, setPosition }: LocationMarker) {
  useMapEvents({
    click(e) {
      setPosition([e.latlng.lat, e.latlng.lng]);
    },
  });

  return position ? <Marker position={position} /> : null;
}

export function CreateEvent() {
  const [step, setStep] = useState(1);
  const [images, setImages] = useState<File[]>([]);
  const [previewUrls, setPreviewUrls] = useState<string[]>([]);
  const [position, setPosition] = useState<[number, number]>([50.8503, 4.3517]);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [date, setDate] = useState('');
  const [time, setTime] = useState('');
  const [address, setAddress] = useState('');
  const [category, setCategory] = useState<'running' | 'hiking' | 'cycling'>('hiking');
  const [facebookUrl, setFacebookUrl] = useState('');
  const [routes, setRoutes] = useState<Route[]>([
    { name: '', distance: 0, price: 0, gpxUrl: '', elevation: 0 }
  ]);
  const [organizerName, setOrganizerName] = useState('');
  const [organizerAddress, setOrganizerAddress] = useState('');
  const [organizerDescription, setOrganizerDescription] = useState('');
  const [organizerWebsite, setOrganizerWebsite] = useState('');
  const [contacts, setContacts] = useState<Contact[]>([
    { name: '', email: '', phone: '', role: 'organizer' }
  ]);

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files) {
      const newFiles = Array.from(e.target.files);
      setImages([...images, ...newFiles]);
      
      // Create preview URLs
      const newPreviewUrls = newFiles.map(file => URL.createObjectURL(file));
      setPreviewUrls([...previewUrls, ...newPreviewUrls]);
    }
  };

  const removeImage = (index: number) => {
    const newImages = images.filter((_, i) => i !== index);
    const newPreviewUrls = previewUrls.filter((_, i) => i !== index);
    
    // Revoke the URL to free up memory
    URL.revokeObjectURL(previewUrls[index]);
    
    setImages(newImages);
    setPreviewUrls(newPreviewUrls);
  };

  const addRoute = () => {
    setRoutes([...routes, { name: '', distance: 0, price: 0, gpxUrl: '', elevation: 0 }]);
  };

  const removeRoute = (index: number) => {
    setRoutes(routes.filter((_, i) => i !== index));
  };

  const updateRoute = (index: number, field: keyof Route, value: string | number) => {
    const newRoutes = [...routes];
    newRoutes[index] = { ...newRoutes[index], [field]: value };
    setRoutes(newRoutes);
  };

  const addContact = () => {
    setContacts([...contacts, { name: '', email: '', phone: '', role: 'organizer' }]);
  };

  const removeContact = (index: number) => {
    setContacts(contacts.filter((_, i) => i !== index));
  };

  const updateContact = (index: number, field: keyof Contact, value: string) => {
    const newContacts = [...contacts];
    newContacts[index] = { ...newContacts[index], [field]: value };
    setContacts(newContacts);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    // Implémenter la soumission du formulaire ici
    alert('Événement créé avec succès !');
  };

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
      <Header />
      <CreateEventHero />
      <div className="container mx-auto px-4 py-24">
        <div className="max-w-4xl mx-auto">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-8">
            Créer un événement
          </h1>

          <div className="flex mb-8">
            <div className={`flex-1 border-b-2 pb-4 ${
              step === 1 ? 'border-[#990047]' : 'border-gray-300'
            }`}>
              <button
                onClick={() => setStep(1)}
                className={`text-lg font-medium ${
                  step === 1 ? 'text-[#990047]' : 'text-gray-500'
                }`}
              >
                1. Informations de l'événement
              </button>
            </div>
            <div className={`flex-1 border-b-2 pb-4 ${
              step === 2 ? 'border-[#990047]' : 'border-gray-300'
            }`}>
              <button
                onClick={() => setStep(2)}
                className={`text-lg font-medium ${
                  step === 2 ? 'text-[#990047]' : 'text-gray-500'
                }`}
              >
                2. Informations de l'organisateur
              </button>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="space-y-8">
            {step === 1 ? (
              <div className="space-y-8">
                {/* Images Upload */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                  <h2 className="text-xl font-semibold mb-4 dark:text-white">Photos de l'événement</h2>
                  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    {previewUrls.map((url, index) => (
                      <div key={index} className="relative aspect-square">
                        <img
                          src={url}
                          alt={`Preview ${index + 1}`}
                          className="w-full h-full object-cover rounded-lg"
                        />
                        <button
                          type="button"
                          onClick={() => removeImage(index)}
                          className="absolute top-2 right-2 bg-red-500 text-white p-1 rounded-full hover:bg-red-600"
                          aria-label="Supprimer l'image"
                        >
                          <Minus size={16} />
                        </button>
                      </div>
                    ))}
                    <label className="aspect-square border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg flex flex-col items-center justify-center cursor-pointer hover:border-[#990047] dark:hover:border-[#990047]">
                      <Upload size={24} className="text-gray-400 dark:text-gray-500" />
                      <span className="mt-2 text-sm text-gray-500 dark:text-gray-400">Ajouter une photo</span>
                      <input
                        type="file"
                        accept="image/*"
                        multiple
                        onChange={handleImageChange}
                        className="hidden"
                      />
                    </label>
                  </div>
                </div>

                {/* Basic Information */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                  <h2 className="text-xl font-semibold mb-4 dark:text-white">Informations générales</h2>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Titre de l'événement
                      </label>
                      <input
                        type="text"
                        id="title"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        aria-label="Titre de l'événement"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Description
                      </label>
                      <textarea
                        id="description"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        rows={4}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        aria-label="Description de l'événement"
                      />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                          Date
                        </label>
                        <input
                          type="date"
                          id="date"
                          value={date}
                          onChange={(e) => setDate(e.target.value)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                          required
                          aria-label="Date de l'événement"
                        />
                      </div>

                      <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                          Heure
                        </label>
                        <input
                          type="time"
                          id="time"
                          value={time}
                          onChange={(e) => setTime(e.target.value)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                          required
                          aria-label="Heure de l'événement"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Catégorie
                      </label>
                      <select
                        id="category"
                        value={category}
                        onChange={(e) => setCategory(e.target.value as 'running' | 'hiking' | 'cycling')}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        aria-label="Catégorie de l'événement"
                      >
                        <option value="running">Course à pied</option>
                        <option value="hiking">Randonnée</option>
                        <option value="cycling">Cyclisme</option>
                      </select>
                    </div>
                  </div>
                </div>

                {/* Location */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                  <h2 className="text-xl font-semibold mb-4 dark:text-white">Localisation</h2>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Adresse
                      </label>
                      <input
                        type="text"
                        id="address"
                        value={address}
                        onChange={(e) => setAddress(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        aria-label="Adresse de l'événement"
                        placeholder="Entrez l'adresse de l'événement"
                      />
                    </div>

                    <div className="h-[300px] rounded-lg overflow-hidden">
                      <MapContainer
                        center={position}
                        zoom={13}
                        className="w-full h-full"
                      >
                        <TileLayer
                          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                        />
                        <LocationMarkerComponent position={position} setPosition={setPosition} />
                      </MapContainer>
                      <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        <MapPin size={16} className="inline mr-1" />
                        Cliquez sur la carte pour définir l'emplacement exact
                      </p>
                    </div>
                  </div>
                </div>

                {/* Routes */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-xl font-semibold dark:text-white">Parcours</h2>
                    <button
                      type="button"
                      onClick={addRoute}
                      className="flex items-center gap-2 text-[#990047] hover:text-[#800039]"
                    >
                      <Plus size={20} />
                      <span>Ajouter un parcours</span>
                    </button>
                  </div>

                  <div className="space-y-4">
                    {routes.map((route, index) => (
                      <div key={index} className="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div className="flex justify-between items-start mb-4">
                          <h3 className="font-medium dark:text-white">Parcours {index + 1}</h3>
                          {routes.length > 1 && (
                            <button
                              type="button"
                              onClick={() => removeRoute(index)}
                              className="text-red-500 hover:text-red-600"
                              aria-label="Supprimer le parcours"
                            >
                              <Minus size={20} />
                            </button>
                          )}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div>
                            <label htmlFor={`route-name-${index}`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Nom du parcours
                            </label>
                            <input
                              type="text"
                              id={`route-name-${index}`}
                              value={route.name}
                              onChange={(e) => updateRoute(index, 'name', e.target.value)}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              required
                              aria-label={`Nom du parcours ${index + 1}`}
                            />
                          </div>

                          <div>
                            <label htmlFor={`route-distance-${index}`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Distance (km)
                            </label>
                            <input
                              type="number"
                              id={`route-distance-${index}`}
                              value={route.distance}
                              onChange={(e) => updateRoute(index, 'distance', parseFloat(e.target.value))}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              required
                              aria-label={`Distance du parcours ${index + 1} en kilomètres`}
                            />
                          </div>

                          <div>
                            <label htmlFor={`route-elevation-${index}`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Dénivelé (m)
                            </label>
                            <input
                              type="number"
                              id={`route-elevation-${index}`}
                              value={route.elevation}
                              onChange={(e) => updateRoute(index, 'elevation', parseFloat(e.target.value))}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              required
                              aria-label={`Dénivelé du parcours ${index + 1} en mètres`}
                            />
                          </div>

                          <div>
                            <label htmlFor={`route-price-${index}`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Prix (€)
                            </label>
                            <input
                              type="number"
                              id={`route-price-${index}`}
                              value={route.price}
                              onChange={(e) => updateRoute(index, 'price', parseFloat(e.target.value))}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              required
                              aria-label={`Prix du parcours ${index + 1} en euros`}
                            />
                          </div>

                          <div>
                            <label htmlFor={`route-gpx-${index}`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Fichier GPX
                            </label>
                            <input
                              type="file"
                              id={`route-gpx-${index}`}
                              accept=".gpx"
                              onChange={(e) => {
                                if (e.target.files?.[0]) {
                                  updateRoute(index, 'gpxUrl', URL.createObjectURL(e.target.files[0]));
                                }
                              }}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              aria-label={`Fichier GPX pour le parcours ${index + 1}`}
                            />
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Social Media */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                  <h2 className="text-xl font-semibold mb-4 dark:text-white">Réseaux sociaux</h2>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                      Lien Facebook de l'événement
                    </label>
                    <input
                      type="url"
                      value={facebookUrl}
                      onChange={(e) => setFacebookUrl(e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                      placeholder="https://facebook.com/events/..."
                    />
                  </div>
                </div>

                <div className="flex justify-end">
                  <button
                    type="button"
                    onClick={() => setStep(2)}
                    className="bg-[#990047] text-white px-6 py-2 rounded-md hover:bg-[#800039] transition-colors duration-200"
                  >
                    Suivant
                  </button>
                </div>
              </div>
            ) : (
              <div className="space-y-8">
                {/* Organizer Information */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                  <h2 className="text-xl font-semibold mb-4 dark:text-white">Informations de l'organisateur</h2>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nom de l'organisation
                      </label>
                      <input
                        type="text"
                        id="organizerName"
                        value={organizerName}
                        onChange={(e) => setOrganizerName(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        aria-label="Nom de l'organisation"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Adresse
                      </label>
                      <input
                        type="text"
                        id="organizerAddress"
                        value={organizerAddress}
                        onChange={(e) => setOrganizerAddress(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        required
                        aria-label="Adresse de l'organisation"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Description
                      </label>
                      <textarea
                        id="organizerDescription"
                        value={organizerDescription}
                        onChange={(e) => setOrganizerDescription(e.target.value)}
                        rows={4}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        aria-label="Description de l'organisation"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Site web
                      </label>
                      <input
                        type="url"
                        id="organizerWebsite"
                        value={organizerWebsite}
                        onChange={(e) => setOrganizerWebsite(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="https://"
                        aria-label="Site web de l'organisation"
                      />
                    </div>
                  </div>
                </div>

                {/* Contact Information */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-xl font-semibold dark:text-white">Contacts</h2>
                    <button
                      type="button"
                      onClick={addContact}
                      className="flex items-center gap-2 text-[#990047] hover:text-[#800039]"
                    >
                      <Plus size={20} />
                      <span>Ajouter un contact</span>
                    </button>
                  </div>

                  <div className="space-y-4">
                    {contacts.map((contact, index) => (
                      <div key={index} className="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div className="flex justify-between items-start mb-4">
                          <h3 className="font-medium dark:text-white">Contact {index + 1}</h3>
                          {contacts.length > 1 && (
                            <button
                              type="button"
                              onClick={() => removeContact(index)}
                              className="text-red-500 hover:text-red-600"
                              aria-label="Supprimer le contact"
                            >
                              <Minus size={20} />
                            </button>
                          )}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div>
                            <label htmlFor={`contact-${index}-name`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Nom
                            </label>
                            <input
                              type="text"
                              id={`contact-${index}-name`}
                              value={contact.name}
                              onChange={(e) => updateContact(index, 'name', e.target.value)}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              required
                              aria-label={`Nom du contact ${index + 1}`}
                            />
                          </div>

                          <div>
                            <label htmlFor={`contact-${index}-phone`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Téléphone
                            </label>
                            <input
                              type="tel"
                              id={`contact-${index}-phone`}
                              value={contact.phone}
                              onChange={(e) => updateContact(index, 'phone', e.target.value)}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              required
                              aria-label={`Téléphone du contact ${index + 1}`}
                            />
                          </div>

                          <div className="md:col-span-2">
                            <label htmlFor={`contact-${index}-email`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Email
                            </label>
                            <input
                              type="email"
                              id={`contact-${index}-email`}
                              value={contact.email}
                              onChange={(e) => updateContact(index, 'email', e.target.value)}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              required
                              aria-label={`Email du contact ${index + 1}`}
                            />
                          </div>

                          <div className="md:col-span-2">
                            <label htmlFor={`contact-${index}-role`} className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                              Rôle
                            </label>
                            <input
                              type="text"
                              id={`contact-${index}-role`}
                              value={contact.role}
                              onChange={(e) => updateContact(index, 'role', e.target.value)}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                              required
                              aria-label={`Rôle du contact ${index + 1}`}
                            />
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="flex justify-between">
                  <button
                    type="button"
                    onClick={() => setStep(1)}
                    className="text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white"
                  >
                    Retour
                  </button>
                  <button
                    type="submit"
                    className="bg-[#990047] text-white px-6 py-2 rounded-md hover:bg-[#800039] transition-colors duration-200"
                  >
                    Créer l'événement
                  </button>
                </div>
              </div>
            )}
          </form>
        </div>
      </div>

      <Footer />
    </div>
  );
}