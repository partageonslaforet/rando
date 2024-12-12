import { ReactNode } from 'react';
import { Outlet } from "react-router-dom";
import { HeaderAdmin } from "@/components/HeaderAdmin";
import { Footer } from "@/components/Footer";

interface AdminLayoutProps {
  children: ReactNode;
}

export function AdminLayout({ children }: AdminLayoutProps) {
  return (
    <div className="min-h-screen flex flex-col bg-[#f3F4F6] dark:bg-gray-900">
      <HeaderAdmin />
      <div className="flex-grow">
        {children || <Outlet />}
      </div>
      <Footer className="-mx-[calc((100vw-100%)/2)] mt-auto" />
    </div>
  );
}
