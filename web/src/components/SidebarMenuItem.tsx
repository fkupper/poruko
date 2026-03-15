import type { ReactNode } from 'react';
import { NavLink } from 'react-router-dom';

interface SidebarMenuItemProps {
  to: string;
  label: string;
  icon?: ReactNode;
}

export function SidebarMenuItem({ to, label, icon }: SidebarMenuItemProps) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) => (isActive ? 'sidebar-nav-link sidebar-nav-link-active' : 'sidebar-nav-link')}
    >
      <span className="sidebar-nav-content">
        {icon !== undefined && <span className="sidebar-nav-icon" aria-hidden="true">{icon}</span>}
        <span>{label}</span>
      </span>
    </NavLink>
  );
}

