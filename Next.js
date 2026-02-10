import { sql } from '@vercel/postgres';

export default async function Page() {
  const { rows } = await sql`SELECT * FROM users`;
  return (
    <div>
      {rows.map((user) => (
        <div key={user.id}>{user.name}</div>
      ))}
    </div>
  );
}