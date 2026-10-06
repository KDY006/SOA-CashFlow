// FbAvatar.jsx - Default Facebook white silhouette user avatar
import React from "react";

export default function FbAvatar({ size = 32, className = "", bgColor = "#e4e6eb" }) {
  return (
    <div
      className={`fb-avatar-bubble ${className}`}
      style={{
        width: size,
        height: size,
        minWidth: size,
        minHeight: size,
        borderRadius: "50%",
        overflow: "hidden",
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: bgColor,
        flexShrink: 0,
        boxShadow: "0 1px 3px rgba(0, 0, 0, 0.06)",
        border: "1px solid rgba(0, 0, 0, 0.06)",
        position: "relative",
      }}
    >
      <svg
        viewBox="0 0 36 36"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        style={{ width: "100%", height: "100%", display: "block" }}
      >
        <circle cx="18" cy="18" r="18" fill={bgColor} />
        {/* Crisp white head */}
        <circle cx="18" cy="13" r="5.5" fill="#ffffff" />
        {/* Crisp white shoulders & body */}
        <path
          d="M7 32.5C7.5 25.5 12 21.5 18 21.5C24 21.5 28.5 25.5 29 32.5C26 35 22 36 18 36C14 36 10 35 7 32.5Z"
          fill="#ffffff"
        />
      </svg>
    </div>
  );
}
