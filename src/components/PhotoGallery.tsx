import React, { useState } from 'react';

interface PhotoGalleryProps {
  images: string[];
}

export function PhotoGallery({ images }: PhotoGalleryProps) {
  const [mainImage, setMainImage] = useState(images[0]);

  if (images.length === 0) return null;

  if (images.length === 1) {
    return (
      <div className="w-full h-[500px] rounded-lg overflow-hidden">
        <img 
          src={images[0]} 
          alt="Event" 
          className="w-full h-full object-cover"
        />
      </div>
    );
  }

  return (
    <div className="grid grid-cols-4 gap-4">
      {/* Main Image */}
      <div className="col-span-4 h-[500px] rounded-lg overflow-hidden">
        <img 
          src={mainImage} 
          alt="Event main" 
          className="w-full h-full object-cover transition-transform duration-300 hover:scale-105"
        />
      </div>

      {/* Thumbnail Grid */}
      <div className="col-span-4 grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
        {images.map((image, index) => (
          <button
            key={index}
            onClick={() => setMainImage(image)}
            className={`
              relative aspect-square rounded-lg overflow-hidden
              ${mainImage === image ? 'ring-2 ring-[#990047]' : ''}
              transition-all duration-200 hover:opacity-90
            `}
          >
            <img 
              src={image} 
              alt={`Thumbnail ${index + 1}`} 
              className="w-full h-full object-cover"
            />
            {mainImage === image && (
              <div className="absolute inset-0 bg-[#990047] bg-opacity-20" />
            )}
          </button>
        ))}
      </div>
    </div>
  );
}