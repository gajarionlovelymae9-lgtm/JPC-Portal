export default function AdminAiMonitoringPage() {
  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">AI &amp; NLP Monitoring</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Artificial Intelligence and Natural Language Processing
        </p>
      </div>

      <section className="bg-white border border-gray-200 rounded-3xl shadow-sm">
        <div className="px-6 py-6 sm:px-8 sm:py-8 space-y-6">
          <div className="flex flex-wrap gap-2">
            <span className="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-indigo-700">
              Artificial Intelligence
            </span>
            <span className="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700">
              Natural Language Processing
            </span>
          </div>

          <p className="text-sm leading-relaxed text-gray-600 sm:text-base">
            Artificial Intelligence (AI) enables the JPC Portal to analyze employee report data and provide
            intelligent insights. Natural Language Processing (NLP) is a branch of AI that enables the system
            to understand, analyze, process, and summarize human language.
          </p>

          <div className="rounded-2xl border border-indigo-100 bg-indigo-50/60 px-5 py-4">
            <h2 className="text-xs font-bold uppercase tracking-wide text-indigo-700">
              JPC Portal usage
            </h2>
            <p className="mt-2 text-sm leading-relaxed text-gray-700">
              In the JPC Portal, AI and NLP help analyze employee reports, generate report summaries, validate
              report content and references, and provide meaningful insights that support administrators and
              deans in monitoring employee progress.
            </p>
          </div>
        </div>
      </section>
    </div>
  )
}
