import React, { Component } from "react";
import {
  View,
  Text,
  Image,
  StyleSheet,
  Dimensions,
  ScrollView,
} from "react-native";

const { width } = Dimensions.get("window");

class Carousel extends Component {
  scrollRef = React.createRef();

  constructor(props) {
    super(props);
    this.state = {
      activeIndex: 0,
    };
  }

  componentDidMount() {
    // Hanya jalankan autoplay jika prop auto adalah true
    if (this.props.auto === true) {
      this.startAutoPlay();
    }
  }

  componentWillUnmount() {
    // Pastikan timer dibersihkan saat komponen unmount
    this.stopAutoPlay();
  }

  componentDidUpdate(prevProps) {
    // Handle perubahan prop auto
    if (prevProps.auto !== this.props.auto) {
      if (this.props.auto === true) {
        this.startAutoPlay();
      } else {
        this.stopAutoPlay();
      }
    }
  }

  startAutoPlay = () => {
    const { interval = 5000 } = this.props;

    // Hanya jalankan jika auto adalah true
    if (this.props.auto === true) {
      this.timer = setInterval(() => {
        const { activeIndex } = this.state;
        const { data } = this.props;
        const nextIndex = (activeIndex + 1) % data.length;

        this.scrollRef.current?.scrollTo({
          x: nextIndex * width,
          animated: true,
        });

        this.setState({ activeIndex: nextIndex });
      }, interval);
    }
  };

  stopAutoPlay = () => {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  };

  handleScroll = (event) => {
    const position = event.nativeEvent.contentOffset.x;
    const index = Math.round(position / width);

    if (this.state.activeIndex !== index) {
      this.setState({ activeIndex: index });
    }
  };

  handleMomentumScrollEnd = (event) => {
    const position = event.nativeEvent.contentOffset.x;
    const index = Math.round(position / width);

    if (this.state.activeIndex !== index) {
      this.setState({ activeIndex: index });

      // Restart autoplay hanya jika auto adalah true
      if (this.props.auto === true) {
        this.stopAutoPlay();
        this.startAutoPlay();
      }
    }
  };

  render() {
    const { data, height = 200, indicatorColor = "#fff", style } = this.props;
    const { activeIndex } = this.state;

    if (!data || data.length === 0) {
      return null;
    }

    // Extract borderRadius from external style if it exists
    const borderRadius = style?.borderRadius || 0;

    return (
      <View style={[styles.container, { height: height + 30 }, style]}>
        <View style={[{ height, overflow: "hidden", borderRadius }]}>
          <ScrollView
            ref={this.scrollRef}
            horizontal
            pagingEnabled
            showsHorizontalScrollIndicator={false}
            onScroll={this.handleScroll}
            onMomentumScrollEnd={this.handleMomentumScrollEnd}
            scrollEventThrottle={16}
            onTouchStart={() => this.props.auto === true && this.stopAutoPlay()}
            onTouchEnd={() => this.props.auto === true && this.startAutoPlay()}
          >
            {data.map((item, index) => (
              <View key={index} style={[styles.slide, { height }]}>
                <Image
                  source={item.image}
                  style={[styles.image, { height }]}
                  resizeMode="cover"
                />
                <View style={styles.overlay}>
                  <Text
                    style={styles.title}
                    numberOfLines={2}
                    ellipsizeMode="tail"
                  >
                    {item.title}
                  </Text>
                </View>
              </View>
            ))}
          </ScrollView>
        </View>

        <View style={styles.paginationContainer}>
          <View style={styles.pagination}>
            {data.map((_, idx) => (
              <View
                key={idx}
                style={[
                  styles.dot,
                  idx === activeIndex
                    ? [styles.activeDot, { backgroundColor: indicatorColor }]
                    : [
                        styles.inactiveDot,
                        { backgroundColor: `${indicatorColor}80` },
                      ],
                ]}
              />
            ))}
          </View>
        </View>
      </View>
    );
  }
}

const styles = StyleSheet.create({
  container: {},
  slide: {
    width: width,
    height: "100%",
  },
  image: {
    width: "100%",
    height: "100%",
  },
  overlay: {
    position: "absolute",
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: "rgba(0, 0, 0, 0.5)",
    padding: 15,
  },
  title: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "bold",
    lineHeight: 22,
  },
  paginationContainer: {
    height: 30,
    justifyContent: "center",
    alignItems: "center",
  },
  pagination: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginHorizontal: 4,
  },
  activeDot: {
    width: 24,
  },
  inactiveDot: {
    opacity: 0.5,
  },
});

export default Carousel;
