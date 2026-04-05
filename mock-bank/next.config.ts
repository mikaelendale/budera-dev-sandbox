import path from 'path';
import type { NextConfig } from 'next';

const isDev = process.env.NODE_ENV === 'development';

// Keep tracing rooted at this app so `/_next/static` chunks always match `mock-bank/.next`
// (parent lockfile may still warn; that is OK for local dev).
const nextConfig: NextConfig = {
  eslint: {
    ignoreDuringBuilds: true,
  },
  outputFileTracingRoot: path.join(__dirname),
  ...(isDev && {
    headers: async () => [
      {
        source: '/_next/static/:path*',
        headers: [
          {
            key: 'Cache-Control',
            value: 'no-store, must-revalidate',
          },
        ],
      },
    ],
  }),
};

export default nextConfig;
