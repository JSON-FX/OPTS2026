export default function ApplicationLogo({ className = '' }: { className?: string }) {
    return <img src="/lgu-seal.png" alt="LGU Quezon" className={`rounded-full ${className}`} />;
}
