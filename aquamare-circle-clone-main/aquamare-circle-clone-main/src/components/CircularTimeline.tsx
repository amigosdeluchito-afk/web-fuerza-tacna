import { useState } from "react";
import { motion } from "motion/react";

type TimelineEvent = { year: string; description: string };

const events: TimelineEvent[] = [
  { year: "2009", description: "Aquamare Marine was established in Plymouth, UK, providing sales and support to the marine industry" },
  { year: "2012", description: "Aquamare Marine launches operations in Australia, expanding its global reach and bringing its expertise to the Southern Hemisphere" },
  { year: "2015", description: "The company relocates its headquarters to a prestigious waterside facility at Turnchapel Wharf, enhancing operational capabilities and client service" },
  { year: "2017", description: "Aquamare Marine USA is established, marking a significant step into the North American market" },
  { year: "2018", description: "Aquamare Marine expands into a new purpose-built unit at the Turnchapel Wharf Waterside Facility, further strengthening its UK operations" },
  { year: "2019", description: "We began operations in Fort Lauderdale, Florida, allowing us to serve clients on the US East Coast" },
  { year: "2020", description: "Aquamare picked up the 'Best in Service' award in the EMEA region from Seakeeper" },
  { year: "2022", description: "Aquamare expanded into the EU market by opening facilities at Port Marina Baie des Anges on the French Riviera" },
  { year: "2023", description: "We were named as 'Dealer of the Year' by Seakeeper for providing a world-class sales service" },
  { year: "2024", description: "Aquamare were named as the UK's top-performing dealer for marine technology maker Garmin" },
  { year: "2025", description: "We launched our waterside refit centre at Mountbatten, Plymouth, with covered storage and direct access to the English Channel" },
];

// Arc spans from -ARC_SPAN/2 to +ARC_SPAN/2 around the top (0deg = top).
const ARC_SPAN = 200; // degrees visible across the arc
const STEP = ARC_SPAN / (events.length - 1);

export function CircularTimeline() {
  const [active, setActive] = useState(0);

  // Rotate so the active item sits at the top (angle 0).
  // Each item base angle = -ARC_SPAN/2 + i*STEP. We rotate by -baseAngle(active).
  const wheelRotation = -(-ARC_SPAN / 2 + active * STEP);

  return (
    <section className="relative w-full overflow-hidden bg-[oklch(0.22_0.03_180)] py-24 text-white">
      <div className="relative mx-auto h-[640px] w-full max-w-[1400px]">
        {/* Center description */}
        <div className="absolute left-1/2 top-[38%] z-10 w-[520px] max-w-[80%] -translate-x-1/2 text-center">
          <motion.p
            key={events[active].year}
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="text-sm leading-relaxed text-white/85 md:text-base"
          >
            {events[active].description}
          </motion.p>
        </div>

        {/* Wheel */}
        <motion.div
          className="absolute left-1/2 top-[110%] aspect-square w-[1700px] -translate-x-1/2 -translate-y-1/2"
          animate={{ rotate: wheelRotation }}
          transition={{ type: "spring", stiffness: 60, damping: 18 }}
        >
          {/* Dotted arc circle */}
          <div
            className="absolute inset-0 rounded-full border border-dashed border-white/25"
            aria-hidden
          />

          {events.map((ev, i) => {
            const angle = -ARC_SPAN / 2 + i * STEP; // 0 = top
            const distance = active === i ? 12 : 0; // active year sits a touch above the dot
            return (
              <button
                key={ev.year}
                type="button"
                onClick={() => setActive(i)}
                className="absolute left-1/2 top-1/2 origin-center"
                style={{
                  transform: `translate(-50%, -50%) rotate(${angle}deg) translateY(-850px)`,
                }}
                aria-label={`Show ${ev.year}`}
              >
                {/* Counter-rotate so labels stay tangent (slight tilt) */}
                <div
                  className="flex flex-col items-center"
                  style={{ transform: `rotate(${-wheelRotation}deg)` }}
                >
                  <motion.span
                    animate={{
                      opacity: active === i ? 1 : 0.55,
                      scale: active === i ? 1.15 : 1,
                      y: active === i ? -distance : 0,
                    }}
                    transition={{ duration: 0.4 }}
                    className="select-none font-serif text-5xl tracking-tight md:text-6xl"
                    style={{
                      transform: `rotate(${angle}deg)`,
                    }}
                  >
                    {ev.year}
                  </motion.span>
                  <span
                    className={`mt-3 block h-3 w-3 rounded-full transition-colors ${
                      active === i ? "bg-[oklch(0.78_0.12_195)]" : "bg-white"
                    }`}
                  />
                </div>
              </button>
            );
          })}
        </motion.div>
      </div>
    </section>
  );
}

export default CircularTimeline;