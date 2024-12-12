import { ReactNode } from 'react';
import { Header } from "@/components/Header";
import { Footer } from "@/components/Footer";
import './MainLayout.css';

interface MainLayoutProps {
  children: ReactNode;
}

interface SectionProps {
  children: ReactNode;
  className?: string;
}

export function Section({ children, className = '' }: SectionProps) {
  return (
    <section className={`${className} main-content`}>
      {children}
    </section>
  );
}

export function Layout({ children }: MainLayoutProps) {
  return (
    <div className="min-h-screen flex flex-col main-container">
      <Header />
      <div className="flex-grow">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {children}
        </div>
      </div>
      <Footer />
    </div>
  );
}
