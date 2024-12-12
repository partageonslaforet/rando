import { cn } from "@/lib/utils";
import { Button } from "./button";
import { LucideIcon } from "lucide-react";

interface SidebarItemProps {
  icon: LucideIcon;
  label: string;
  active: boolean;
  onClick: () => void;
  className?: string;
}

function SidebarItem({ icon: Icon, label, active, onClick, className }: SidebarItemProps) {
  return (
    <Button
      variant="ghost"
      className={cn(
        "w-full justify-start gap-2",
        active && "bg-muted",
        className
      )}
      onClick={onClick}
    >
      <Icon className="h-5 w-5" />
      {label}
    </Button>
  );
}

interface SidebarProps {
  items: {
    icon: LucideIcon;
    label: string;
    value: string;
    className?: string;
  }[];
  value: string;
  onChange: (value: string) => void;
}

export function Sidebar({ items, value, onChange }: SidebarProps) {
  return (
    <div className="h-full min-w-[200px] border-r border-border bg-card">
      <div className="space-y-1">
        {items.map((item) => {
          const Icon = item.icon;
          return (
            <button
              key={item.value}
              onClick={() => onChange(item.value)}
              className={cn(
                "flex items-center w-full space-x-2 rounded-lg px-3 py-2 transition-all",
                value === item.value
                  ? "bg-secondary/10"
                  : "hover:bg-secondary/10",
                "text-black"
              )}
            >
              <Icon className="h-5 w-5 text-primary" />
              <span>{item.label}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
}
