interface DebugProps {
  data: unknown;
  label: string;
}

export function Debug({ data, label }: DebugProps) {
  return (
    <div className="m-4 p-4 border-4 border-red-500 bg-white">
      <h2 className="text-xl font-bold text-black mb-2">Debug: {label}</h2>
      <pre className="whitespace-pre-wrap bg-gray-100 p-4 rounded text-black overflow-auto max-h-96">
        {JSON.stringify(data, null, 2)}
      </pre>
    </div>
  );
}
