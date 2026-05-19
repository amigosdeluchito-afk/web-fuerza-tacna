import { createFileRoute } from "@tanstack/react-router";
import { CircularTimeline } from "@/components/CircularTimeline";

export const Route = createFileRoute("/")({
  component: Index,
});

function Index() {
  return (
    <main className="min-h-screen bg-[oklch(0.22_0.03_180)]">
      <CircularTimeline />
    </main>
  );
}
