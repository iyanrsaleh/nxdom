import React, { useState } from "react";
import { View } from "react-native";

const Grid = ({
  children,
  columns = 2,
  spacing = 10,
  style,
  contentContainerStyle,
}) => {
  const rows = Math.ceil(React.Children.count(children) / columns);
  const gridItems = [];

  for (let i = 0; i < rows; i++) {
    const row = [];
    for (let j = 0; j < columns; j++) {
      const index = i * columns + j;
      if (index < React.Children.count(children)) {
        row.push(
          <View
            key={index}
            style={{
              width: `${100 / columns}%`,
              paddingHorizontal: spacing / 2,
              paddingVertical: spacing / 2,
            }}
          >
            {React.Children.toArray(children)[index]}
          </View>
        );
      }
    }
    gridItems.push(
      <View key={i} style={{ flexDirection: "row", width: "100%" }}>
        {row}
      </View>
    );
  }

  return (
    <View style={[{ width: "100%", margin: -spacing / 2 }, style]}>
      {gridItems}
    </View>
  );
};

export default Grid;
