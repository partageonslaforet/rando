import React from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  createColumnHelper,
  flexRender,
  getCoreRowModel,
  useReactTable,
  getSortedRowModel,
  getPaginationRowModel,
} from '@tanstack/react-table';
import { Check, X, Mail, Ban } from 'lucide-react';
import { AdminLayout } from '../../components/admin/AdminLayout';
import type { User } from '../../types/admin';

const columnHelper = createColumnHelper<User>();

const columns = [
  columnHelper.accessor('email', {
    header: 'Email',
    cell: info => info.getValue(),
  }),
  columnHelper.accessor('name', {
    header: 'Nom',
    cell: info => info.getValue(),
  }),
  columnHelper.accessor('role', {
    header: 'Rôle',
    cell: info => (
      <span className={`px-2 py-1 rounded-full text-sm ${
        info.getValue() === 'admin' 
          ? 'bg-purple-100 text-purple-800' 
          : 'bg-gray-100 text-gray-800'
      }`}>
        {info.getValue()}
      </span>
    ),
  }),
  columnHelper.accessor('isVerified', {
    header: 'Vérifié',
    cell: info => info.getValue() ? (
      <Check className="text-green-500" />
    ) : (
      <X className="text-red-500" />
    ),
  }),
  columnHelper.accessor('createdAt', {
    header: 'Inscrit le',
    cell: info => new Date(info.getValue()).toLocaleDateString(),
  }),
  columnHelper.display({
    id: 'actions',
    header: 'Actions',
    cell: props => (
      <div className="flex gap-2">
        <button 
          className="p-2 text-blue-600 hover:bg-blue-50 rounded-full"
          onClick={() => {/* Send email */}}
        >
          <Mail size={18} />
        </button>
        <button 
          className="p-2 text-red-600 hover:bg-red-50 rounded-full"
          onClick={() => {/* Ban user */}}
        >
          <Ban size={18} />
        </button>
      </div>
    ),
  }),
];

export function AdminUsers() {
  const { data: users = [] } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: async () => {
      // Fetch users from API
      return [] as User[];
    },
  });

  const table = useReactTable({
    data: users,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
  });

  return (
    <AdminLayout>
      <div className="p-6">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold dark:text-white">Utilisateurs</h1>
          <button className="bg-[#990047] text-white px-4 py-2 rounded-md hover:bg-[#800039] transition-colors duration-200">
            Exporter
          </button>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 dark:bg-gray-700">
                {table.getHeaderGroups().map(headerGroup => (
                  <tr key={headerGroup.id}>
                    {headerGroup.headers.map(header => (
                      <th 
                        key={header.id}
                        className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                      >
                        {flexRender(
                          header.column.columnDef.header,
                          header.getContext()
                        )}
                      </th>
                    ))}
                  </tr>
                ))}
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-600">
                {table.getRowModel().rows.map(row => (
                  <tr 
                    key={row.id}
                    className="hover:bg-gray-50 dark:hover:bg-gray-700"
                  >
                    {row.getVisibleCells().map(cell => (
                      <td 
                        key={cell.id}
                        className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"
                      >
                        {flexRender(
                          cell.column.columnDef.cell,
                          cell.getContext()
                        )}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="px-6 py-4 flex items-center justify-between border-t dark:border-gray-600">
            <div className="flex gap-2">
              <button
                onClick={() => table.previousPage()}
                disabled={!table.getCanPreviousPage()}
                className="px-3 py-1 rounded border dark:border-gray-600 disabled:opacity-50"
              >
                Précédent
              </button>
              <button
                onClick={() => table.nextPage()}
                disabled={!table.getCanNextPage()}
                className="px-3 py-1 rounded border dark:border-gray-600 disabled:opacity-50"
              >
                Suivant
              </button>
            </div>
            <span className="text-sm text-gray-700 dark:text-gray-300">
              Page {table.getState().pagination.pageIndex + 1} sur{' '}
              {table.getPageCount()}
            </span>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}